<?php

namespace Autum\SAML\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LightSaml\Model\Protocol\AuthnRequest;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\URL;

class SamlController extends BaseController
{

    protected function authenticateUser($user)
    {
        return auth('web')->login($user);
    }

    protected function redirectToIdp($targetUrl)
    {
        return !empty(config('fortify')) ? response('', 409)->header('X-Inertia-Location', $targetUrl) : redirect()->to($targetUrl);
    }

    protected function redirectTo($targetUrl)
    {
        return redirect()->to($targetUrl);
    }

    protected function fromServiceLoginURL()
    {
        return route(config('saml.signin_route', 'signin'));
    }

    protected function toDestinationIdpURL()
    {
        return config('saml.autum_acs_url', 'https://autum.com.br/login');
    }

    protected function logoutIdpURL()
    {
        return config('saml.autum_sls_url', 'https://autum.com.br/logout') . '?return_to=' . config('saml.slo_redirect');
    }

    protected function fromIssuerURL()
    {
        return URL::to('/');
    }

    protected function afterLoginRedirectRoute()
    {
        return route(config('saml.auth_redirect_route'));
    }

    protected function defaultRedirectRoute()
    {
        return route(config('saml.redirect_route'));
    }

    protected function getUser($data)
    {

        $keys = [
            'emailaddress' => 'email',
            'CommonName' => 'name',
            'surname' => 'lastname',
            'privatepersonalidentifier' => 'id',
            'nameidentifier' => 'username',
            'role' => 'profile',
            'name' => 'fullname',
            'givenname' => 'givenname'
        ];

        $data = collect($data)->mapWithKeys(function($item, $key) use ($keys) {
            return [ $keys[basename($key)] => $item ];
        });

        $user = \App\Models\User::where('id', $data->get('id'))->first();

        if(empty($user)) {
            $user = \App\Models\User::make($data->all());
            $user->forceFill(['id' => $data->get('id')])->save();
        }else {
            $diff = $data->diffAssoc($user->only([
                'name',
                'username',
                'lastname',
                'email',
            ]));

            if(!$diff->isEmpty()) {
                $user->update($diff->all());
            }
        }

        return $user;
    }
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function signin(Request $request)
    {
        
        if($request->filled('SAMLResponse'))
        {
            $xml = base64_decode($request->input('SAMLResponse'));

            $deserializationContext = new \LightSaml\Model\Context\DeserializationContext();
            $deserializationContext->getDocument()->loadXML($xml);

            $response = new \LightSaml\Model\Protocol\Response();
            $response->deserialize(
                $deserializationContext->getDocument()->firstChild,
                $deserializationContext
            );

            $cert = storage_path('samlidp/cert.pem');
            $key = storage_path('samlidp/key.pem');

            // load you key par credential
            $credential = new \LightSaml\Credential\X509Credential(
                \LightSaml\Credential\X509Certificate::fromFile($cert),
                \LightSaml\Credential\KeyHelper::createPrivateKey($key, '', true)
            );

            // decrypt the Assertion with your credential
            $decryptDeserializeContext = new \LightSaml\Model\Context\DeserializationContext();
            /** @var \LightSaml\Model\Assertion\EncryptedAssertionReader $reader */
            $reader = $response->getFirstEncryptedAssertion();

            $assertion = $reader->decryptMultiAssertion([$credential], $decryptDeserializeContext);

            // use decrypted assertion
            $data = [];
            foreach ($assertion->getFirstAttributeStatement()->getAllAttributes() as $attribute) {
                $data[$attribute->getName()] = $attribute->getFirstAttributeValue();
            }
            
            $this->authenticateUser(
                $this->getUser($data)
            );

            $this->redirectTo($this->afterLoginRedirectRoute());
        }

        return $this->redirectTo($this->defaultRedirectRoute());
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $cert = storage_path('samlidp/cert.pem');
        $key = storage_path('samlidp/key.pem');
        $certificate = \LightSaml\Credential\X509Certificate::fromFile($cert);
        $privateKey = \LightSaml\Credential\KeyHelper::createPrivateKey($key, '', true);

        
        $authnRequest = new AuthnRequest();
        $authnRequest
            ->setAssertionConsumerServiceURL($this->fromServiceLoginURL())
            ->setProviderName('Autum')
            ->setProtocolBinding(\LightSaml\SamlConstants::BINDING_SAML2_HTTP_POST)
            ->setID(\LightSaml\Helper::generateID())
            ->setIssueInstant(new \DateTime())
            ->setDestination($this->toDestinationIdpURL())
            ->setIssuer(new \LightSaml\Model\Assertion\Issuer($this->fromIssuerURL()))
            ->setSignature(new \LightSaml\Model\XmlDSig\SignatureWriter($certificate, $privateKey));

            $bindingFactory = new \LightSaml\Binding\BindingFactory();
            $redirectBinding = $bindingFactory->create(\LightSaml\SamlConstants::BINDING_SAML2_HTTP_REDIRECT);

            $messageContext = new \LightSaml\Context\Profile\MessageContext();
            $messageContext->setMessage($authnRequest);

        /** @var \Symfony\Component\HttpFoundation\RedirectResponse $httpResponse */
        return $redirectBinding->send($messageContext);

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        auth()->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->away($this->logoutIdpURL());
    }

}
