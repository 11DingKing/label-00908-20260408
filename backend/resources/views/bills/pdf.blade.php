<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>账单 {{ $bill->bill_number }}</title>
    <style>
        body { font-family: 'SimHei', 'STHeiti', sans-serif; font-size: 12px; color: #333; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #409EFF; padding-bottom: 15px; }
        .header h1 { font-size: 22px; color: #409EFF; margin: 0 0 5px 0; }
        .header p { margin: 2px 0; color: #666; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 4px 8px; vertical-align: top; }
        .info-table .label { color: #909399; width: 100px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th { background: #409EFF; color: #fff; padding: 8px; text-align: left; }
        .items-table td { padding: 8px; border-bottom: 1px solid #EBEEF5; }
        .items-table tr:nth-child(even) { background: #F5F7FA; }
        .summary { text-align: right; margin-top: 20px; }
        .summary .row { margin: 4px 0; }
        .summary .total { font-size: 16px; font-weight: bold; color: #409EFF; border-top: 2px solid #409EFF; padding-top: 8px; margin-top: 8px; }
        .status { display: inline-block; padding: 2px 10px; border-radius: 4px; font-size: 11px; }
        .status-paid { background: #f0f9eb; color: #67C23A; }
        .status-pending { background: #fdf6ec; color: #E6A23C; }
        .status-overdue { background: #fef0f0; color: #F56C6C; }
        .footer { margin-top: 40px; text-align: center; color: #909399; font-size: 10px; border-top: 1px solid #EBEEF5; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>账单</h1>
        <p>账单编号：{{ $bill->bill_number }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">用户：</td>
            <td>{{ $bill->user->name ?? '-' }} ({{ $bill->user->email ?? '-' }})</td>
            <td class="label">状态：</td>
            <td>
                <span class="status status-{{ $bill->status }}">
                    @switch($bill->status)
                        @case('paid') 已支付 @break
                        @case('pending') 待支付 @break
                        @case('overdue') 已逾期 @break
                        @default {{ $bill->status }}
                    @endswitch
                </span>
            </td>
        </tr>
        <tr>
            <td class="label">订阅计划：</td>
            <td>{{ $bill->subscription?->plan?->name ?? '-' }}</td>
            <td class="label">到期日：</td>
            <td>{{ $bill->due_date?->format('Y-m-d') ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">账期：</td>
            <td colspan="3">{{ $bill->period_start?->format('Y-m-d') }} 至 {{ $bill->period_end?->format('Y-m-d') }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>项目类型</th>
                <th>描述</th>
                <th>数量</th>
                <th>单价</th>
                <th>金额</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bill->items as $item)
            <tr>
                <td>{{ $item->item_type === 'subscription' ? '订阅费用' : '使用量费用' }}</td>
                <td>{{ $item->description }}</td>
                <td>{{ $item->quantity ? number_format($item->quantity, 2) : '-' }}</td>
                <td>{{ $item->unit_price ? '¥' . number_format($item->unit_price, 4) : '-' }}</td>
                <td>¥{{ number_format($item->amount, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center; color: #909399;">暂无明细</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <div class="row">订阅费用：¥{{ number_format($bill->subscription_fee, 2) }}</div>
        <div class="row">使用量费用：¥{{ number_format($bill->usage_fee, 2) }}</div>
        @if($bill->discount > 0)
        <div class="row">折扣：-¥{{ number_format($bill->discount, 2) }}</div>
        @endif
        @if($bill->tax > 0)
        <div class="row">税费：¥{{ number_format($bill->tax, 2) }}</div>
        @endif
        <div class="row total">应付总额：¥{{ number_format($bill->total_amount, 2) }}</div>
    </div>

    <div class="footer">
        <p>此账单由系统自动生成 · {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>
