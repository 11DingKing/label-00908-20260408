<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use App\Modules\Payment\Gateways\AlipayGateway;
use App\Modules\Payment\Gateways\StripeGateway;
use App\Modules\Payment\Gateways\WechatPayGateway;

class PaymentGatewayManager
{
    protected array $gateways = [];

    public function gateway(string $name): PaymentGatewayInterface
    {
        if (!isset($this->gateways[$name])) {
            $this->gateways[$name] = $this->resolveGateway($name);
        }
        return $this->gateways[$name];
    }

    public function getAvailableGateways(): array
    {
        return array_keys(config('payment.gateways', []));
    }

    protected function resolveGateway(string $name): PaymentGatewayInterface
    {
        return match ($name) {
            'stripe', 'credit_card' => new StripeGateway(),
            'alipay' => new AlipayGateway(),
            'wechat' => new WechatPayGateway(),
            default => throw new PaymentGatewayException($name, "不支持的支付网关: {$name}"),
        };
    }
}
