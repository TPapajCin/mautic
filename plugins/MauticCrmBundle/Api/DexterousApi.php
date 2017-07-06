<?php

namespace MauticPlugin\MauticCrmBundle\Api;

use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\PluginBundle\Exception\ApiErrorException;

class DexterousApi extends CrmApi
{

    protected function request($endpoint, $parameters = [], $method = 'GET', $object = null)
    {
        $keys = $this->integration->getKeys();
        if (isset($keys['password'])) {
//             $parameters['password'] = $keys['password'];
//             $parameters['username'] = $keys['username'];
        }
        
        $apiUrl = $keys['site'];
        $url = sprintf('%s/%s', $apiUrl, $endpoint);
        
        $response = $this->integration->makeRequest($url, $parameters, $method, ['encode_parameters' => 'json']);
        
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
    public function getLeadFields($object = 'contact')
    {
        $keys = $this->integration->getKeys();
        if (!isset($keys['site']))
            return [];
        if ($object == 'contacts') {
            $object = 'contact';
        }

        return $this->request(sprintf('mobile/api/%s/properties.json', $object), [], 'GET', $object);
    }

    /**
     * Creates Dexterous lead.
     *
     * @param array $data
     *
     * @return mixed
     */
    public function createLead(array $data, $lead, $updateLink = false)
    {
        $result = [];
        //Format data for request
        $formattedLeadData = $this->integration->formatLeadDataForCreateOrUpdate($data, $lead, $updateLink);
        $result = $this->request('mobile/api/contact.json', $formattedLeadData, 'POST');
        
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
        return $this->request('mobile/api/contact.json', $params, 'GET', 'contacts');
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
        if (!isset($this->keys['site'])) {
            return [];
        }
        return $this->request('v1/contacts/properties', ['name' => $propertyName,  'groupName' => 'contactinformation', 'type' => 'string'], 'POST', $object);
    }
}
