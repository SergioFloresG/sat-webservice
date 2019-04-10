<?php
namespace MrGenis\Sat\Webservice\Services;

use MrGenis\Sat\Webservice\Exception\SatWsException;
use MrGenis\Sat\Webservice\Models\SatWsVerificationResponse;
use MrGenis\Sat\Webservice\SatWsSession;
use MrGenis\Sat\Webservice\SatWsUtils as utils;
use MrGenis\Library\XmlToArray;


class SatWsVerificationRequester extends SignaturesAndCertificatesWs
{

    /**
     * @param SatWsSession $session
     * @param string       $id_solicitud
     *
     * @return SatWsVerificationResponse
     * @throws SatWsException
     */
    public function request(SatWsSession $session, $id_solicitud)
    {
        $options = [
            'id_solicitud' => $id_solicitud,
            'rfc'          => $session->cer_information['subject']['x500UniqueIdentifier'],
        ];
        $body = $this->makeRequestBody($session, $options);

        $curl = utils::curl_init(
            'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/VerificaSolicitudDescargaService.svc',
            'http://DescargaMasivaTerceros.sat.gob.mx/IVerificaSolicitudDescargaService/VerificaSolicitudDescarga',
            $body, $session->session_token);

        set_time_limit(0);
        $soap = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new SatWsException('SAT:Ws:Request Verify Error: ' . $err);
        }

        $response = $this->parseResponse($soap);

        return $response;
    }

    protected function makeRequestBody(SatWsSession $session, array $data)
    {
        static $body_template = <<< BT
<s:Envelope xmlns:des="http://DescargaMasivaTerceros.sat.gob.mx" xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xd="http://www.w3.org/2000/09/xmldsig#">
	<s:Header/>
	<s:Body>
		<des:VerificaSolicitudDescarga>
			<des:solicitud IdSolicitud="{{id_solicitud}}" RfcSolicitante="{{rfc}}">
				<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">
					<SignedInfo>
						<CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
						<SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/>
						<Reference URI="">
							<Transforms>
								<Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
							</Transforms>
							<DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
							<DigestValue>{{digest_value}}</DigestValue>
						</Reference>
					</SignedInfo>
                    <SignatureValue>{{signature_value}}</SignatureValue>
                    {{keyinfo_node}}
                </Signature>
			</des:solicitud>
		</des:VerificaSolicitudDescarga>
	</s:Body>
</s:Envelope>
BT;

        $data['digest_value'] = $this->makeDigestValue($data);
        $data['signature_value'] = $this->signatureValue($data['digest_value'], $session->key_pem);
        $data['keyinfo_node'] = $this->keyInfoX509Node($session);

        return utils::injectInXmlTemplate($data, $body_template);
    }

    /**
     * @param array $data
     *
     * @return string
     */
    protected function makeDigestValue(array $data)
    {
        static $digest_template = <<< DT
<des:VerificaSolicitudDescarga xmlns:des="http://DescargaMasivaTerceros.sat.gob.mx">
    <des:solicitud IdSolicitud="{{id_solicitud}}" RfcSolicitante="{{rfc}}"></des:solicitud>
</des:VerificaSolicitudDescarga>
DT;

        $digest = utils::injectInXmlTemplate($data, $digest_template);

        return json_encode(sha1($digest, true));
    }


    private function parseResponse($response)
    {
        $data = XmlToArray::convert($response);

        if (isset($data['Body']['Fault'])) {
            $code = $data['Body']['Fault']['faultcode'];
            $message = $data['Body']['Fault']['faultstring']['_text'];
            throw new SatWsException("{$code}: {$message}");
        }
        /** @var SatWsVerificationResponse $result */
        $result = new \stdClass();
        $solicitud = $data["Body"]["VerificaSolicitudDescargaResponse"]["VerificaSolicitudDescargaResult"];
        $result->paquete_estado = intval($solicitud['_attributes']['EstadoSolicitud']);
        $result->estado = intval($solicitud['_attributes']["CodEstatus"]);
        $result->mensaje = $solicitud['_attributes']["Mensaje"];

        $result->numero_cfdi = intval($solicitud['_attributes']['NumeroCFDIs']);
        if ($result->paquete_estado === 3) {
            $result->paquetes = $solicitud["IdsPaquetes"];
            if (!is_array($result->paquetes)) {
                $result->paquetes = [$result->paquetes];
            }
        }


        return $result;
    }

}