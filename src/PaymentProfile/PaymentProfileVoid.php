<?php

namespace ANet\PaymentProfile;

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetControllers;
use ANet\AuthorizeNet;

class PaymentProfileVoid extends AuthorizeNet
{
    public function handle($refsTransId, $paymentProfileId)
    {
        $paymentProfile = new AnetAPI\PaymentProfileType();
        $paymentProfile->setPaymentProfileId($paymentProfileId);

        // Set the transaction's refId
        $customerProfile = new AnetAPI\CustomerProfilePaymentType();
        $customerProfile->setCustomerProfileId($this->user->anet()->getCustomerProfileId());
        $customerProfile->setPaymentProfile($paymentProfile);

        $paymentProfile = new AnetAPI\PaymentProfileType();
        $paymentProfile->setPaymentProfileId($paymentProfileId);

        $transactionRequestType = new AnetAPI\TransactionRequestType();
        $transactionRequestType->setTransactionType("voidTransaction");

        $transactionRequestType->setProfile($customerProfile);
        $transactionRequestType->setRefTransId($refsTransId);

        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($this->getMerchantAuthentication());
        $request->setRefId($this->getRefId());
        $request->setTransactionRequest($transactionRequestType);

        $controller = new AnetControllers\CreateTransactionController($request);
        return $this->execute($controller);
    }
}
