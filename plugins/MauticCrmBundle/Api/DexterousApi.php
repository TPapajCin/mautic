<?php

namespace MauticPlugin\MauticCrmBundle\Api;

use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\PluginBundle\Exception\ApiErrorException;

class DexterousApi extends CrmApi
{

    protected function request($operation, $parameters = [], $method = 'GET')
    {
        
        if (isset($this->keys['password'])) {
            $parameters['password'] = $this->keys['password'];
            $parameters['username'] = $this->keys['username'];
        }
        $apiUrl = $this->integration->getApiUrl();
        
        $url = sprintf('%s/%s', $apiUrl, $endpoint);
        
        $response = $this->integration->makeRequest($url, $parameters, $method, ['encode_parameters' => 'json', 'cw-app-id' => $this->integration->getCompanyCookieKey()]);
        
        if (is_array($response) && !empty($response['status']) && $response['status'] == 'error') {
            throw new ApiErrorException($response['error']);
        } elseif (is_array($response) && !empty($response['errors'])) {
            $errors = [];
            foreach ($response['errors'] as $error) {
                $errors[] = $error['message'];
            }
            
            throw new ApiErrorException(implode(' ', $errors));
        } else {
            return $response;
        }
    }

    /**
     * @return mixed
     */
    public function getLeadFields($object = 'contacts')
    {
        if ($object == 'company') {
            $object = 'companies'; //hubspot company object name
        }

        return $this->request('v2/properties', [], 'GET', $object);
    }

    /**
     * Creates Hubspot lead.
     *
     * @param array $data
     *
     * @return mixed
     */
    public function createLead(array $data, $lead, $updateLink = false)
    {
        /*
         * As Hubspot integration requires a valid email
         * If the email is not valid we don't proceed with the request
         */
        $email  = $data['email'];
        $result = [];
        //Check if the is a valid email
        MailHelper::validateEmail($email);
        //Format data for request
        $formattedLeadData = $this->integration->formatLeadDataForCreateOrUpdate($data, $lead, $updateLink);
        if ($formattedLeadData) {
            $result = $this->request('v1/contact/createOrUpdate/email/'.$email, $formattedLeadData, 'POST');
        }

        return $result;
    }

    /**
     * gets Hubspot contact.
     *
     * @param array $data
     *
     * @return mixed
     */
    public function getContacts($params = [])
    {
        return $this->request('mobile/api/contact.json?', $params, 'GET', 'contacts');
    }

    /**
     * gets Hubspot company.
     *
     * @param array $data
     *
     * @return mixed
     */
    public function getCompanies($params, $id)
    {
        if ($id) {
            return $this->request('v2/companies/'.$id, $params, 'GET', 'companies');
        }

        return $this->request('v2/companies/recent/modified', $params, 'GET', 'companies');
    }

    /**
     * @param        $propertyName
     * @param string $object
     *
     * @return mixed|string
     */
    public function createProperty($propertyName, $object = 'properties')
    {
        return $this->request('v1/contacts/properties', ['name' => $propertyName,  'groupName' => 'contactinformation', 'type' => 'string'], 'POST', $object);
    }
}
