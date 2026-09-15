import { reactive } from 'vue'

/**
 * 入站字段映射 可视化 ↔ JSON 模型 的双向转换与校验。
 * 对应后端 third_channels.receive_mapping（入口分段 order/verify）。
 * 设计见：docs/需求与设计/渠道管理全配置化接入-前端设计.md
 */

// B 内部参考字段清单（供"内部字段"下拉选择与提示）
export const referenceFields = [
  { label: '订单号', value: 'order_no' },
  { label: '用户手机', value: 'user_phone' },
  { label: '业务编码', value: 'bus_code' },
  { label: '产品编码', value: 'sku_code' },
  { label: '单价', value: 'price' },
  { label: '数量', value: 'quantity' },
  { label: '总金额', value: 'total_amount' },
  { label: '订单时间', value: 'order_time' },
  { label: '会员昵称', value: 'user_nick' },
  { label: '会员状态', value: 'user_status' },
  { label: '省份编码', value: 'province_code' },
  { label: '城市编码', value: 'city_code' },
  { label: '会员类型', value: 'user_type' },
  { label: '用户来源', value: 'user_source' },
]

export const converterOptions = [
  { label: '无(原样)', value: null },
  { label: '时间(datetime)', value: 'datetime' },
  { label: '数值(numeric)', value: 'numeric' },
  { label: '整数(int)', value: 'int' },
  { label: '布尔(bool)', value: 'bool' },
  { label: '枚举映射(enum)', value: 'enum' },
  { label: '拼接(concat)', value: 'concat' },
  { label: '去除字符(strip)', value: 'strip' },
]

// 单行 UI 模型
const emptyRow = () => ({
  internal: '',
  source: '',
  convert: null,
  convertParam: '',
  defaultValue: null,
  ignore: false,
})

// 构建分段（order/verify）UI 状态
const makeSection = () => reactive({
  rows: [],
  raw: false,
})

/**
 * 初始化完整 UI 模型（含 order/verify 两段）。
 */
export function createReceiveMappingState() {
  return {
    order: makeSection(),
    verify: makeSection(),
  }
}

/**
 * 从后端 receive_mapping 加载到 UI 模型。
 */
export function loadReceiveMapping(state, mapping) {
  const clear = (section) => { section.rows = []; section.raw = false }
  clear(state.order)
  clear(state.verify)

  if (!mapping) return

  for (const entry of ['order', 'verify']) {
    const sec = state[entry]
    const cfg = (typeof mapping[entry] === 'string') ? safeParse(mapping[entry]) : (mapping[entry] || {})
    if (!cfg || typeof cfg !== 'object') continue

    sec.raw = !!cfg.__raw__
    for (const [internal, expr] of Object.entries(cfg)) {
      if (internal === '__raw__') continue
      const row = emptyRow()
      row.internal = internal
      if (typeof expr === 'string') {
        row.source = expr
      } else if (expr && typeof expr === 'object') {
        row.source = expressArraySource(expr)
        row.convert = expr.convert || null
        row.convertParam = convertParamOf(expr)
        row.defaultValue = expr.default != null ? String(expr.default) : null
        row.ignore = !!expr.ignore
      }
      sec.rows.push(row)
    }
  }
}

/**
 * 从 UI 模型提取后端 receive_mapping JSON。
 */
export function extractReceiveMapping(state) {
  const out = {}
  for (const entry of ['order', 'verify']) {
    const sec = state[entry]
    const cfg = {}
    sec.rows.forEach(r => {
      if (!r.internal || !r.internal.trim()) return
      cfg[r.internal.trim()] = buildExpression(r)
    })
    if (sec.raw) cfg.__raw__ = true
    if (Object.keys(cfg).length > 0) {
      out[entry] = cfg
    }
  }
  return Object.keys(out).length > 0 ? out : null
}

/**
 * 校验 UI 模型是否合法（内部字段必填；来源与默认至少其一）。
 */
export function validateReceiveMapping(state) {
  const errors = []
  for (const entry of ['order', 'verify']) {
    state[entry].rows.forEach((r, i) => {
      if (!r.internal || !r.internal.trim()) {
        errors.push(`${entry  === 'order' ? '收单' : '验证码'} #${i + 1}: 内部字段不能为空`)
      } else if (!r.source && r.defaultValue === null && !r.convert) {
        errors.push(`${entry  === 'order' ? '收单' : '验证码'} #${i + 1}(${r.internal}): 需填写来源或默认值`)
      }
    })
  }
  return errors
}

// ---- 内部工具 ----

function safeParse(str) {
  try { return JSON.parse(str) } catch { return null }
}

// 从数组规格推导来源字符串展示（优先 from，其次 value，其次候选未拆解的原样）
function expressArraySource(expr) {
  if (expr.from != null) return expr.from
  if (expr.value != null) return `值:${expr.value}`
  if (Array.isArray(expr.parts)) return expr.parts.join('|')
  return ''
}

function convertParamOf(expr) {
  const c = expr.convert
  if (c === 'datetime') return expr.format || ''
  if (c === 'enum') return expr.map ? JSON.stringify(expr.map) : ''
  if (c === 'concat') return expr.parts ? expr.parts.join('|') : ''
  if (c === 'strip') return Array.isArray(expr.chars) ? expr.chars.join('') : (expr.chars || '')
  return ''
}

function buildExpression(row) {
  // 来源里有"值:"前缀 → {value} 固定值
  if (row.source && row.source.startsWith('值:')) {
    const expr = { value: row.source.slice(2) }
    if (row.defaultValue !== null) expr.default = normalizeDefault(row.defaultValue)
    return expr
  }

  const expr = {}
  if (row.source && row.source.trim()) {
    expr.from = row.source.trim()
  }
  if (row.convert) {
    expr.convert = row.convert
    if (row.convert === 'datetime') {
      if (row.convertParam) expr.format = row.convertParam
    } else if (row.convert === 'enum') {
      const map = safeParse(row.convertParam)
      if (map) expr.map = map
    } else if (row.convert === 'concat') {
      const parts = (row.convertParam || '').split('|').map(s => s.trim()).filter(Boolean)
      if (parts.length) expr.parts = parts
    } else if (row.convert === 'strip') {
      if (row.convertParam) expr.chars = row.convertParam.split('')
    }
  }
  if (row.defaultValue !== null) expr.default = normalizeDefault(row.defaultValue)
  if (row.ignore) expr.ignore = true

  return expr
}

function normalizeDefault(v) {
  const s = String(v).trim()
  if (s === '') return null
  if (/^-?\d+$/.test(s)) return Number(s)
  if (/^-?\d*\.\d+$/.test(s)) return Number(s)
  // 其余按字符串
  return s
}