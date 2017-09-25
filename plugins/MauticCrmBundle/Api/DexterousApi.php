<?php

namespace MauticPlugin\MauticCrmBundle\Api;

use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\PluginBundle\Exception\ApiErrorException;
use Symfony\Component\VarDumper\VarDumper;

class DexterousApi extends CrmApi
{

    /**
     * 
     * @param unknown $endpoint
     * @param array $parameters
     * @param string $method
     * @param unknown $object
     * @throws ApiErrorException
     * @return mixed|string
     */
    protected function request($endpoint, $parameters = [], $method = 'GET', $object = null)
    {
        $keys = $this->integration->getKeys();
        if (isset($keys['password'])) {
//             $parameters['password'] = $keys['password'];
//             $parameters['username'] = $keys['username'];
        }
        
        $apiUrl = $keys['site'];
        $url = sprintf('%s/%s', $apiUrl, $endpoint);

        $params = [];
        $params['encode_parameters'] = 'json';
        if ($object == 'folder')
        {
            
            $params['return_raw'] = true;
        }
        
        $response = $this->integration->makeRequest($url, $parameters, $method, $params);
        
        if (isset($response->headers['Location']))
        {
            
            $params = [];
            $params['encode_parameters'] = 'json';
            $response = $this->integration->makeRequest($response->headers['Location'].'.json', [], 'GET', $params);
        }
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
        if (in_array($object, ['contact','contacts'])) {
            return json_decode('[{"name":"id","label":"id"},{"name":"firstName","label":"firstName"},{"name":"lastName","label":"lastName"},{"name":"email","label":"email"},{"name":"company","label":"company"},{"name":"information","label":"information"},{"name":"mobilePhone","label":"mobilePhone"},{"name":"phone","label":"phone"},{"name":"tags","label":"tags"}]', true);
            
        }
        
        if ($object == 'folders')
        {
            return [['name' => 'id', 'label' => 'id']];
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
        $keys = $this->integration->getKeys();
        $settings = $this->integration->getIntegrationSettings()->getFeatureSettings();
        $formattedLeadData = $this->integration->formatLeadDataForCreateOrUpdate($data, $lead, $updateLink);
        
        if (isset($settings['folderCreate']) && ($settings['folderCreate']))
        {
            $departmentId = isset($settings['departmentId'])?$settings['departmentId']:1;
            $typeId = isset($settings['typeId'])?$settings['typeId']:1;
            $formattedFolderData = ['job' => [ 'name' => $formattedLeadData['email'], 'department' => $departmentId, 'type' => $typeId]];

            $result = $this->request('api/core/jobs.json', $formattedFolderData, 'POST', 'folder');
            if (isset($result['id'])) 
            {
                $metaData = [];
                foreach ($lead->getFields() as $category => $fields)
                {
                    foreach ($fields as $k => $val)
                    {
                        $metaData[] = ['key' => $val['label'], 'value' => $val['value']];
                    }
                }
                $formattedMetaData = ['meta' => $metaData];
                $metaResult = $this->request('api/core/jobs/'.$result['id'].'/meta.json', $formattedMetaData, 'PUT', 'folder');
            }
        } else
        {
            
            $result  = $this->request('mobile/api/contact.json', $formattedLeadData, 'POST');
            
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
