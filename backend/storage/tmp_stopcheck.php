$d = App\Models\ForwardStop::orderBy('id', 'desc')->paginate(2)->toArray();
echo 'keys=' . implode(',', array_keys($d)) . "\n";
echo 'data_count=' . count($d['data']) . "\n";
echo 'total=' . $d['total'] . "\n";