<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticSocialBundle\Integration;

use Symfony\Component\VarDumper\VarDumper;

/**
 * Class DexterousIntegration.
 */
class DexterousIntegration extends SocialIntegration
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'DexterousAuth';
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifierFields()
    {
        return [
            'dexterous',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedFeatures()
    {
        return [
            'login_button',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthenticationUrl()
    {
        return 'http://13concepts/oauth/v2/auth';
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenUrl()
    {
        return 'http://13concepts/oauth/v2/token';
    }

    /**
     * @return string
     */
    public function getAuthScope()
    {
        return 'email';
    }

    /**
     * {@inheritdoc}
     *
     * @param string $data
     * @param bool   $postAuthorization
     *
     * @return mixed
     */
    public function parseCallbackResponse($data, $postAuthorization = false)
    {
        // Facebook is inconsistent in that it returns errors as json and data as parameter list
        $values = parent::parseCallbackResponse($data, $postAuthorization);

        if (null === $values) {
            parse_str($data, $values);

            $this->session->set($this->getName().'_tokenResponse', $values);
        }

        return $values;
    }

    /**
     * @param $endpoint
     *
     * @return string
     */
    public function getApiUrl($endpoint)
    {
        return "http://13concepts/api/" .$endpoint. ".json";
    }

    /**
     * Get public data.
     *
     * @param $identifier
     * @param $socialCache
     *
     * @return array
     */
    public function getUserData($identifier, &$socialCache)
    {
        $this->persistNewLead = false;
        $accessToken          = $this->getContactAccessToken($socialCache);

        if (!isset($accessToken['access_token'])) {
            return;
        }

        $url    = $this->getApiUrl('core/user/me');
        $fields = array_keys($this->getAvailableLeadFields());
        VarDumper::dump($fields);
die();
        $parameters = [
            'access_token' => $accessToken['access_token'],
            'fields'       => implode(',', $fields),
        ];

        $data = $this->makeRequest($url, $parameters, 'GET', ['auth_type' => 'rest']);

        if (is_object($data) && isset($data->id)) {
            $info = $this->matchUpData($data);

            if (isset($data->username)) {
                $info['profileHandle'] = $data->username;
            } elseif (isset($data->link)) {
                if (preg_match("/www.facebook.com\/(app_scoped_user_id\/)?(.*?)($|\/)/", $data->link, $matches)) {
                    $info['profileHandle'] = $matches[2];
                }
            } else {
                $info['profileHandle'] = $data->id;
            }
            $info['profileImage'] = "https://graph.facebook.com/{$data->id}/picture?type=large";

            $socialCache['id']          = $data->id;
            $socialCache['profile']     = $info;
            $socialCache['lastRefresh'] = new \DateTime();
            $socialCache['accessToken'] = $this->encryptApiKeys($accessToken);

            $this->getMauticLead($info, $this->persistNewLead, $socialCache, $identifier);

            return $data;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableLeadFields($settings = [])
    {
        return [
            'email'       => ['type' => 'string'],
            'name'        => ['type' => 'string'],
        ];
    }
}
