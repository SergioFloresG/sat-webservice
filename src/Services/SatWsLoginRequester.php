<?php
namespace MrGenis\Sat\Webservice\Services;

use MrGenis\Sat\Webservice\Exception\SatWsException;
use MrGenis\Sat\Webservice\Models\SatWsLoginResponse;
use MrGenis\Sat\Webservice\SatWsSession;
use MrGenis\Sat\Webservice\SatWsUtils as utils;

class SatWsLoginRequester
{

    protected static $expiration_time = 300;

    /**
     * Establece la cantidad de segundos que el token de session se mantiene activo.
     * De forma predeterminada son 5min (300s).
     *
     * @param $seconds
     */
    public static function defaultExpirationTime($seconds)
    {
        static::$expiration_time = $seconds;
    }

    /**
     * @param SatWsSession $session
     *
     * @return SatWsLoginResponse
     */
    public function login(SatWsSession &$session)
    {
        $uuid = 'uuid-' . utils::uuidV4() . '-1';
        $body = $this->makeRequestBody($session, $uuid);
        $curl = utils::curl_init(
            'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/Autenticacion/Autenticacion.svc',
            'http://DescargaMasivaTerceros.gob.mx/IAutenticacion/Autentica',
            $body);

        set_time_limit(0);
        $soap = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new SatWsException('SAT:Ws:Login Error: ' . $err);
        }

        $response = $this->parseResponse($soap);
        $session->session_succes = $response->success;
        if ($response->success) {
            $session->session_token = $response->token;
            $session->session_created = $response->created;
            $session->session_expires = $response->expires;
        }

        return $response;
    }

    /**
     * @param string $response
     *
     * @return SatWsLoginResponse
     */
    private function parseResponse($response)
    {
        $data = utils::xml2Array($response);

        if (isset($data['Body']['Fault'])) {
            $code = $data['Body']['Fault']['faultcode'];
            $message = $data['Body']['Fault']['faultstring']['_text'];
            throw new SatWsException("{$code}: {$message}");
        }

        /** @var SatWsLoginResponse $result */
        $result = new \stdClass();
        $result->success = true;
        $result->token = $data['Body']['AutenticaResponse']['AutenticaResult'];
        $result->created = strtotime($data['Header']['Security']['Timestamp']['Created']);
        $result->expires = strtotime($data['Header']['Security']['Timestamp']['Expires']);

        return $result;
    }

    private function makeRequestBody(SatWsSession $login, $uuid)
    {

        static $body_template = <<< BEOF
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:u="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
	<s:Header>
		<o:Security s:mustUnderstand="1" xmlns:o="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
			<u:Timestamp u:Id="_0">
				<u:Created>{{timestamp_created}}</u:Created>
				<u:Expires>{{timestamp_expires}}</u:Expires>
			</u:Timestamp>
			<o:BinarySecurityToken u:Id="{{uuid}}" 
			    ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3" 
			    EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">{{binary_security_token}}</o:BinarySecurityToken>
			<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">
				<SignedInfo>
					<CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
					<SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/>
					<Reference URI="#_0">
						<Transforms>
							<Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
						</Transforms>
						<DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
						<DigestValue>{{digest_value}}</DigestValue>
					</Reference>
				</SignedInfo>
				<SignatureValue>{{signature_value}}</SignatureValue>
				<KeyInfo>
					<o:SecurityTokenReference>
						<o:Reference ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3" URI="#{{uuid}}"/>
					</o:SecurityTokenReference>
				</KeyInfo>
			</Signature>
		</o:Security>
	</s:Header>
	<s:Body>
    <Autentica xmlns="http://DescargaMasivaTerceros.gob.mx" />
	</s:Body>
</s:Envelope>
BEOF;

        $data = [
            'uuid' => $uuid
        ];

        $timez = time() - date('Z');
        $data['timestamp_created'] = date("Y-m-d\TH:i:s\.v\Z", $timez);
        $data['timestamp_expires'] = date("Y-m-d\TH:i:s\.v\Z", $timez + static::$expiration_time);
        unset($timez);

        $data['digest_value'] = $this->makeDigestValue($data);
        $data['signature_value'] = $this->makeSignature($data, $login->key_pem);
        $data['binary_security_token'] = base64_encode($login->cer);

        return utils::injectInXmlTemplate($data, $body_template);
    }

    private function makeDigestValue(array $data)
    {
        static $digest_template = <<< DIT
<u:Timestamp xmlns:u="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" u:Id="_0">
    <u:Created>{{timestamp_created}}</u:Created>
    <u:Expires>{{timestamp_expires}}</u:Expires>
</u:Timestamp>
DIT;

        $digest = utils::injectInXmlTemplate($data, $digest_template);

        return base64_encode(sha1($digest, true));
    }

    private function makeSignature(array $data, $keyPem)
    {
        static $signed_info_template = <<< SIT
<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#">
    <CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"></CanonicalizationMethod>
    <SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"></SignatureMethod>
    <Reference URI="#_0">
        <Transforms>
            <Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"></Transform>
        </Transforms>
        <DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></DigestMethod>
        <DigestValue>{{digest_value}}</DigestValue>
    </Reference>
</SignedInfo>
SIT;


        $signed = utils::injectInXmlTemplate($data, $signed_info_template);
        openssl_sign($signed, $signature, $keyPem, OPENSSL_ALGO_SHA1);

        return base64_encode($signature);
    }
}