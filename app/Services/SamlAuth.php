<?php

namespace App\Services;

use Storage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use LightSaml\Model\Protocol\Response;
use LightSaml\Credential\X509Certificate;
// For debug purposes, include the Log facade
use Illuminate\Support\Facades\Log;

trait SamlAuth
{
    /*
    |--------------------------------------------------------------------------
    | File handling (metadata, certificates)
    |--------------------------------------------------------------------------
    */

    /**
     * Get either the url or the content of a given file.
     */
    protected function getSamlFile($configPath, $url)
    {
        if ($url) {
            return Storage::disk('saml')->url($configPath);
        }

        return Storage::disk('saml')->get($configPath);
    }

    /**
     * Get either the url or the content of the saml metadata file.
     *
     * @param bool url   Set to true to get the metadata url, otherwise the
     *                      file content will be returned. Defaults to false.
     *
     * @return string with either the url or the content
     */
    protected function metadata($url = false)
    {
        return $this->getSamlFile(config('saml.idp.metadata'), $url);
    }

    /**
     * Get either the url or the content of the certificate file.
     *
     * @param bool url   Set to true to get the certificate url, otherwise the
     *                      file content will be returned. Defaults to false.
     *
     * @return string with either the url or the content
     */
    protected function certfile($url = false)
    {
        return $this->getSamlFile(config('saml.idp.cert'), $url);
    }

    /**
     * Get either the url or the content of the certificate keyfile.
     *
     * @param bool url   Set to true to get the certificate key url, otherwise
     *                      the file content will be returned. Defaults to false.
     *
     * @return string with either the url or the content
     */
    protected function keyfile($url = false)
    {
        return $this->getSamlFile(config('saml.idp.key'), $url);
    }

    /*
    |--------------------------------------------------------------------------
    | Saml authentication
    |--------------------------------------------------------------------------
    */

    /**
     * Handle an http request as saml authentication request. Note that the method
     * should only be called in case a saml request is also included.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function handleSamlLoginRequest($request)
    {
        // Store RelayState to session if provided
        if (!empty($request->input('RelayState'))) {
            session()->put('RelayState', $request->input('RelayState'));
        }
        // Handle SamlRequest if provided, otherwise just exit
        if (isset($request->SAMLRequest)) {
            // Get and decode the SAML request
            $SAML = $request->SAMLRequest;
            $decoded = base64_decode($SAML);
            $xml = gzinflate($decoded);
            // Initiate context and authentication request object
            $deserializationContext = new \LightSaml\Model\Context\DeserializationContext();
            $deserializationContext->getDocument()->loadXML($xml);
            $authnRequest = new \LightSaml\Model\Protocol\AuthnRequest();
            $authnRequest->deserialize($deserializationContext->getDocument()->firstChild, $deserializationContext);
            // Generate the saml response (saml authentication attempt)
            $this->buildSamlResponse($authnRequest, $request);
        }
    }

    /**
     * Make a saml authentication attempt by building the saml response.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     *
     * @see https://www.lightsaml.com/LightSAML-Core/Cookbook/How-to-make-Response/
     * @see https://imbringingsyntaxback.com/implementing-a-saml-idp-with-laravel/
     */
    protected function buildSamlResponse($authnRequest, $request)
    {
        // Get corresponding destination and issuer configuration from SAML config file for assertion URL
        // Note: Simplest way to determine the correct assertion URL is a short debug output on first run
        $destination = config('saml.sp.'.base64_encode($authnRequest->getAssertionConsumerServiceURL()).'.destination');
        $issuer = config('saml.sp.'.base64_encode($authnRequest->getAssertionConsumerServiceURL()).'.issuer');

        // Load in both certificate and keyfile
        // The files are stored within a private storage path, this prevents from
        // making them accessible from outside
        $x509 = new X509Certificate();
        $certificate = $x509->loadPem($this->certfile());
        // Load in keyfile content (last parameter determines of the first one is a file or its content)
        $privateKey = \LightSaml\Credential\KeyHelper::createPrivateKey($this->keyfile(), '', false);

        if (config('saml.debug_saml_request')) {
            Log::debug('<SamlAuth::buildSAMLResponse>');
            Log::debug('Assertion URL: '.$authnRequest->getAssertionConsumerServiceURL());
            Log::debug('Assertion URL: '.base64_encode($authnRequest->getAssertionConsumerServiceURL()));
            Log::debug('Destination: '.$destination);
            Log::debug('Issuer: '.$issuer);
            Log::debug('Certificate: '.$this->certfile());
        }

        // Generate the response objec

        // We are responding with both the email and the username as attributes
        // TODO: Add here other attributes, e.g. groups / roles / permissions
        $roles = array();
        if (\Auth::check()) {
            $user = \Auth::user();
            $email = $user->email;
            $name = $user->name;
            if (config('saml.forward_roles')) {
                $roles = $user->roles->pluck('name')->all();
            }
        } else {
            $email = $request['email'];
            $name = 'Place Holder';
        }

        $restriction = config(
                            'saml.sp.'.base64_encode($authnRequest->getAssertionConsumerServiceURL()).'.audience_restriction',
                            $authnRequest->getAssertionConsumerServiceURL()
                        );
        // Send out the saml response
        $response = '<?xml version="1.0"?>
        <samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" ID="'.\LightSaml\Helper::generateID().'"
        Version="2.0" IssueInstant="'.Carbon::now()->format('Y-m-d\TH:i:s\Z').'" Destination="'.$destination.'">
            <saml:Issuer xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion">'.$issuer.'</saml:Issuer>
            <samlp:Status>
                <samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/>
            </samlp:Status>
            <Assertion xmlns="urn:oasis:names:tc:SAML:2.0:assertion" ID="'.\LightSaml\Helper::generateID().'"
                    Version="2.0" IssueInstant="'.Carbon::now()->format('Y-m-d\TH:i:s\Z').'">
                <Issuer>'.$issuer.'</Issuer>
                <Subject>
                    <NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">'.$email.'</NameID>
                    <SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">
                        <SubjectConfirmationData InResponseTo="'.$authnRequest->getId().'"
                            NotOnOrAfter="'.Carbon::parse('+2 minutes')->format('Y-m-d\TH:i:s\Z').'" Recipient="'.$authnRequest->getAssertionConsumerServiceURL().'"/>
                    </SubjectConfirmation>
                </Subject>
                <Conditions NotBefore="'.Carbon::now()->format('Y-m-d\TH:i:s\Z').'" NotOnOrAfter="'.Carbon::parse('+1 minute')->format('Y-m-d\TH:i:s\Z').'">
                    <AudienceRestriction>
                        <Audience>'.$restriction.'</Audience>
                    </AudienceRestriction>
                </Conditions>
                <AttributeStatement>
                    <Attribute Name="http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress">
                        <AttributeValue>'.$email.'</AttributeValue>
                    </Attribute>
                    <Attribute Name="http://schemas.xmlsoap.org/claims/CommonName">
                        <AttributeValue>'.$name.'</AttributeValue>
                    </Attribute>
                </AttributeStatement>
                <AuthnStatement AuthnInstant="'.Carbon::now()->subMinutes(10)->format('Y-m-d\TH:i:s\Z').'" SessionIndex="_some_session_index">
                    <AuthnContext>
                        <AuthnContextClassRef>
                            urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport
                        </AuthnContextClassRef>
                    </AuthnContext>
                </AuthnStatement>
            </Assertion>
        </samlp:Response>';
        Log::debug($response);
        $data = ['SAMLResponse' => base64_encode($response)];
        if ($state = $this->hasRelayStateToResponse()) {
            $data['RelayState'] = $state;
        }
        session(compact('data', 'destination'));
    }

    /**
     * @param $response
     */
    protected function hasRelayStateToResponse()
    {
        return session()->has('RelayState') ? session()->pull('RelayState') : false;
    }
}
