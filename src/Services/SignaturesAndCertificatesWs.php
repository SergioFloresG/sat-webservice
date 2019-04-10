<?php
namespace MrGenis\Sat\Webservice\Services;

use MrGenis\Sat\Webservice\SatWsSession;
use MrGenis\Sat\Webservice\SatWsUtils as utils;

abstract class SignaturesAndCertificatesWs
{

    /**
     * @param array $data
     *
     * @return string
     */
    abstract protected function makeDigestValue(array $data);

    /**
     * @param SatWsSession $session
     * @param array        $option
     *
     * @return string
     */
    abstract protected function makeRequestBody(SatWsSession $session, array $option);

    /**
     * @param SatWsSession $session
     *
     * @return string XML
     */
    protected function keyInfoX509Node(SatWsSession $session)
    {
        static $key_info_x509_template = <<< KIX509
<KeyInfo>
    <X509Data>
        <X509IssuerSerial>
            <X509IssuerName>{{cert_issuer}}</X509IssuerName>
            <X509SerialNumber>{{serial_number}}</X509SerialNumber>
        </X509IssuerSerial>
        <X509Certificate>{{cert_b64}}</X509Certificate>
    </X509Data>
</KeyInfo>
KIX509;

        $data = [
            'cert_b64'      => base64_encode($session->cer),
            'serial_number' => $session->cer_information['serialNumber'],
        ];

        $cert_issuer = $session->cer_information['issuer'];
        $data['cert_issuer'] = array_reduce(array_keys($cert_issuer), function ($carry, $key) use ($cert_issuer) {
            return sprintf('%s%s="%s",', $carry, $key, htmlspecialchars($cert_issuer[$key]));
        }, '');
        $data['cert_issuer'] = substr($data['cert_issuer'], 0, -1);

        return utils::injectInXmlTemplate($data, $key_info_x509_template);
    }


    /**
     * @param string $digest_value
     * @param string $keyPem
     *
     * @return string
     */
    protected function signatureValue($digest_value, $keyPem)
    {
        static $signed_info_template = <<< SIT
<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#">
    <CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"></CanonicalizationMethod>
    <SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"></SignatureMethod>
    <Reference URI="">
        <Transforms><Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"></Transform></Transforms>
        <DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></DigestMethod>
        <DigestValue>{{digest_value}}</DigestValue>
    </Reference>
</SignedInfo>
SIT;

        $text = utils::injectInXmlTemplate(compact('digest_value'), $signed_info_template);
        openssl_sign($text, $signature, $keyPem, OPENSSL_ALGO_SHA1);

        return base64_encode($signature);
    }

}