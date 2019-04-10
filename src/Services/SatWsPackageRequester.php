<?php
namespace MrGenis\Sat\Webservice\Services;

use MrGenis\Sat\Webservice\Exception\SatWsException;
use MrGenis\Sat\Webservice\SatWsSession;
use MrGenis\Sat\Webservice\SatWsUtils as utils;

class SatWsPackageRequester extends SignaturesAndCertificatesWs
{

    public function request(SatWsSession $session, string $paquete_id)
    {
        $options = [
            'paquete_id' => $paquete_id,
            'rfc'        => $session->cer_rfc
        ];
        $body = $this->makeRequestBody($session, $options);

        $curl = utils::curl_init(
            'https://cfdidescargamasiva.clouda.sat.gob.mx/DescargaMasivaService.svc',
            'http://DescargaMasivaTerceros.sat.gob.mx/IDescargaMasivaTercerosService/Descargar',
            $body, $session->session_token);

        set_time_limit(0);
        $soap = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new SatWsException('SAT:Ws:Request Package Error: ' . $err);
        }

        $response = $this->parseResponse($soap);

        return $response;
    }


    /**
     * @param array $data
     *
     * @return string
     */
    protected function makeDigestValue(array $data)
    {
        static $digest_template = <<< DT
<des:PeticionDescargaMasivaTercerosEntrada xmlns:des="http://DescargaMasivaTerceros.sat.gob.mx">
    <des:peticionDescarga IdPaquete="{{paquete_id}}" RfcSolicitante="{{rfc}}"></des:peticionDescarga>
</des:PeticionDescargaMasivaTercerosEntrada>
DT;
        $digest = utils::injectInXmlTemplate($data, $digest_template);

        return base64_encode(sha1($digest, true));
    }

    protected function makeRequestBody(SatWsSession $session, array $data)
    {
        static $body_template = <<< BT
<s:Envelope xmlns:des="http://DescargaMasivaTerceros.sat.gob.mx" xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xd="http://www.w3.org/2000/09/xmldsig#">
	<s:Header/>
	<s:Body>
		<des:PeticionDescargaMasivaTercerosEntrada>
			<des:peticionDescarga IdPaquete="{{paquete_id}}" RfcSolicitante="{{rfc}}">
				<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">
					<SignedInfo>
						<CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
						<SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/>
						<Reference URI="#_0">
							<Transforms><Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/></Transforms>
							<DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
							<DigestValue>{{digest_value}}</DigestValue>
						</Reference>
					</SignedInfo>
					<SignatureValue>{{signature_value}}</SignatureValue>
					{{keyinfo_node}}
				</Signature>
			</des:peticionDescarga>
		</des:PeticionDescargaMasivaTercerosEntrada>
	</s:Body>
</s:Envelope>
BT;

        $data['digest_value'] = $this->makeDigestValue($data);
        $data['signature_value'] = $this->signatureValue($data['digest_value'], $session->key_pem);
        $data['keyinfo_node'] = $this->keyInfoX509Node($session);

        return utils::injectInXmlTemplate($data, $body_template);
    }

    /**
     * @param string $response
     *
     * @return string Informacion del paquete en base 64
     */
    private function parseResponse(string $response)
    {
        $data = utils::xml2Array($response);

        if (isset($data["Body"]["Fault"])) {
            $code = $data["Body"]["Fault"]["faultcode"];
            $string = $data["Body"]["Fault"]["faultstring"];
            throw new SatWsException("{$code}: {$string}");
        }

        $paquete = $data["Body"]["RespuestaDescargaMasivaTercerosSalida"]["Paquete"];

        return $paquete;
    }
}