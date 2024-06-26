<?php

namespace ANet\PaymentProfile;

use ANet\AuthorizeNet;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetControllers;

class PaymentProfile extends AuthorizeNet
{
    public function create($opaqueData, array $source)
    {
        $merchantKeys = $this->getMerchantAuthentication();

        $opaqueDataType = new AnetAPI\OpaqueDataType();
        $opaqueDataType->setDataDescriptor($opaqueData['dataDescriptor']);
        $opaqueDataType->setDataValue($opaqueData['dataValue']);

        $paymentType = new AnetAPI\PaymentType();
        $paymentType->setOpaqueData($opaqueDataType);

        // https://github.com/AuthorizeNet/sample-code-php/blob/master/CustomerProfiles/create-customer-payment-profile.php
        // Create the Bill To info for new payment type
        $billto = new AnetAPI\CustomerAddressType();
        $billto->setFirstName($opaqueData['billingFirstName']);
        $billto->setLastName($opaqueData['billingLastName']);

        $customerPaymentProfileType = new AnetAPI\CustomerPaymentProfileType;
        $customerPaymentProfileType->setPayment($paymentType);
        $customerPaymentProfileType->setBillTo($billto);

        // Assemble the complete transaction request
        $paymentProfileRequest = new AnetAPI\CreateCustomerPaymentProfileRequest();
        $paymentProfileRequest->setMerchantAuthentication($merchantKeys);

        // Add an existing profile id to the request
        $paymentProfileRequest->setCustomerProfileId($this->user->anet()->getCustomerProfileId());
        $paymentProfileRequest->setPaymentProfile($customerPaymentProfileType);

        // Create the controller and get the response
        $controller = new AnetControllers\CreateCustomerPaymentProfileController($paymentProfileRequest);
        $response = $this->execute($controller);

        if (!is_null($response->getCustomerPaymentProfileId())) {
            $this->storeInDatabase($response, $source);
        }

        return $response;
    }

    /**
     * @param $response
     * @param array $source
     * @return mixed
     */
    public function storeInDatabase($response, $source)
    {
        return \DB::table('user_payment_profiles')->updateOrInsert(
            [
                'user_id'               => $this->user->id,
                'payment_profile_id'    => $response->getCustomerPaymentProfileId(),
            ],
            [
                'last_4'                => $source['last_4'],
                'expiry_date'           => $source['expiry_date'],
                'brand'                 => $source['brand'],
                'type'                  => $source['type']
            ],
        );
    }
}
