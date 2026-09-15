<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 推送失败通知
 * 支持多渠道通知：数据库、邮件、Slack（可扩展）
 */
class PushFailureNotification extends Notification
{
    use Queueable;

    /**
     * 失败详情
     */
    protected array $failureDetails;

    /**
     * @param  array  $failureDetails  失败详情
     */
    public function __construct(array $failureDetails)
    {
        $this->failureDetails = $failureDetails;
    }

    /**
     * 获取通知发送渠道
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // 默认通过数据库存储，可按需添加 mail/slack 等渠道
        $channels = ['database'];

        // 如果通知对象有 email 且配置了邮件通知，添加邮件渠道
        if ($notifiable->email && config('notifications.push_failure.mail', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * 获取邮件通知内容
     */
    public function toMail(object $notifiable): MailMessage
    {
        $detail = $this->failureDetails;

        $orderNoList = is_array($detail['order_nos'] ?? null)
            ? implode(', ', array_slice($detail['order_nos'], 0, 10))
            : ($detail['order_no'] ?? 'N/A');

        $errorMsg = $detail['sample_error'] ?? $detail['error'] ?? '未知错误';

        return (new MailMessage)
            ->subject('推送失败通知 - '.$detail['channel_name'] ?? '未知渠道')
            ->line('以下订单推送失败：')
            ->line('渠道：'.($detail['channel_name'] ?? 'N/A'))
            ->line('失败数量：'.($detail['total_failures'] ?? 0).' 条')
            ->line('错误信息：'.$errorMsg)
            ->line('失败时间：'.($detail['failed_at'] ?? now()->toDateTimeString()))
            ->line('订单号：'.$orderNoList)
            ->action('查看详情', url('/admin/orders'))
            ->line('请及时处理。');
    }

    /**
     * 获取数据库通知内容
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->failureDetails;
    }
}