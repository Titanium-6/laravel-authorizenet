<?php

namespace ANet\CustomerProfile;

use ANet\AuthorizeNet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

class CustomerProfile extends AuthorizeNet
{
    /**
     * it will talk to authorize.net and provide some basic information so, that the user can be charged.
     * @param User $user
     * @return AnetAPI\ANetApiResponseType
     * @throws \Exception
     */
    public function create()
    {
        $customerProfileDraft   = $this->draftCustomerProfile();
        $request                = $this->draftRequest($customerProfileDraft);
        $controller             = new AnetController\CreateCustomerProfileController($request);

        $response               = $this->execute($controller);
        $response               = $this->handleCreateCustomerResponse($response);

        if (method_exists($response, 'getCustomerProfileId')) {
            $this->persistInDatabase($response->getCustomerProfileId());
        } else if (isset($response->profile_id) && $response->profile_id) {
            $this->persistInDatabase($response->profile_id);
        }

        return $response;
    }

    /**
     * @param AnetAPI\CreateCustomerProfileResponse $response
     * @return AnetAPI\ANetApiResponseType
     * @throws \Exception
     */
    protected function handleCreateCustomerResponse(AnetAPI\CreateCustomerProfileResponse $response)
    {
        if (is_null($response->getCustomerProfileId())) {
            if (app()->environment() == 'local') {
                dd(
                    $response->getMessages()->getMessage()[0]->getText()
                );
            }

            Log::debug($response->getMessages()->getMessage()[0]->getText());

            // Check For Duplicate Profile and return response
            $error_code = $response->getMessages()->getMessage()[0]->getCode();

            if ($error_code == 'E00039') {
                $re = '/A duplicate record with ID (?<profileId>[0-9]+) already exists/m';
                $str = $response->getMessages()->getMessage()[0]->getText();

                preg_match($re, $str, $matches);

                $profile_id = $matches['profileId'] ?? '';

                return (object)['status' => true, 'profile_id' => $profile_id];
            }

            throw new \Exception('Failed, To create customer profile. ' . $response->getMessages()->getMessage()[0]->getText());
        }

        return $response;
    }

    /**
     * @param string $customerProfileId
     * @param User $user
     * @return bool
     */
    protected function persistInDatabase(string $customerProfileId): bool
    {
        return \DB::table('user_gateway_profiles')->updateOrInsert(
            [
                'user_id' => $this->user->id
            ],
            [
                'profile_id' => $customerProfileId,
            ],
        );
    }

    /**
     * @param User $user
     * @return AnetAPI\CustomerProfileType
     */
    protected function draftCustomerProfile(): AnetAPI\CustomerProfileType
    {
        $customerProfile = new AnetAPI\CustomerProfileType();
        $customerProfile->setDescription("Customer Profile");
        $customerProfile->setMerchantCustomerId($this->user->id);
        $customerProfile->setEmail($this->user->email);
        return $customerProfile;
    }

    /**
     * @param AnetAPI\CustomerProfileType $customerProfile
     * @return AnetAPI\CreateCustomerProfileRequest
     */
    protected function draftRequest(AnetAPI\CustomerProfileType $customerProfile): AnetAPI\CreateCustomerProfileRequest
    {
        $request = new AnetAPI\CreateCustomerProfileRequest();

        $request->setMerchantAuthentication($this->getMerchantAuthentication());
        $request->setRefId($this->getRefId());
        $request->setProfile($customerProfile);

        return $request;
    }
}
