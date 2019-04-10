<?php
namespace MrGenis\Sat\Webservice\Services;

use MrGenis\Sat\Webservice\Exception\SatWsException;
use MrGenis\Sat\Webservice\Models\SatWsConstants as constants;
use MrGenis\Sat\Webservice\Models\SatWsDownloadResponse;
use MrGenis\Sat\Webservice\SatWsSession;
use MrGenis\Sat\Webservice\SatWsUtils as utils;

/**
 * Class SatWsRequestDownload
 * @package MrGenis\Sat\Webservice\Services
 */
class SatWsDownloadRequester extends SignaturesAndCertificatesWs
{

    /**
     * @param SatWsSession $session
     * @param string       $fecha_inicial
     * @param string       $fecha_final
     * @param string       $tipo_solicitud <em>recibidas</em> o <em>emitidas</em>
     *
     * @param string       $solicitud <em>CFDI<em> o <em>METADATA</em>
     *
     * @return SatWsDownloadResponse
     */
    public function request(SatWsSession $session, $fecha_inicial, $fecha_final, $tipo_solicitud, $solicitud = 'CFDI')
    {
        if ($tipo_solicitud === constants::DOWNLOAD_TYPE_RECEIVED) {
            $rfc_tipo = 'RfcReceptor';
        } else if ($tipo_solicitud === constants::DOWNLOAD_TYPE_EMITED) {
            $rfc_tipo = 'RfcEmisor';
        } else {
            throw new SatWsException('El tipo de solicitud es invalido. Se espera "recibidas" o "emitidas".');
        }

        if (!in_array($solicitud, constants::DOWNLOAD_REQUESTS)) {
            throw new SatWsException('El tipo de solicitud es invalido. Se recibio "' . $solicitud . '", se espera ' . join(' o ', constants::DOWNLOAD_REQUESTS));
        }

        $options = [
            'rfc_tipo'      => $rfc_tipo,
            'rfc'           => $session->cer_information['subject']['x500UniqueIdentifier'],
            'fecha_inicial' => gmdate('Y-m-d\TH:i:s\.v\Z', strtotime($fecha_inicial)),
            'fecha_final'   => gmdate('Y-m-d\TH:i:s\.v\Z', strtotime($fecha_final)),
            'solicitud'     => $solicitud
        ];


        $body = $this->makeRequestBody($session, $options);
        $curl = utils::curl_init(
            'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/SolicitaDescargaService.svc',
            'http://DescargaMasivaTerceros.sat.gob.mx/ISolicitaDescargaService/SolicitaDescarga',
            $body, $session->session_token);

        set_time_limit(0);
        $soap = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new SatWsException('SAT:Ws:Request Download Error: ' . $err);
        }

        $response = $this->parseResponse($soap);

        return $response;
    }


    protected function makeRequestBody(SatWsSession $session, array $data)
    {
        static $body_template = <<< BTMENV
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:des="http://DescargaMasivaTerceros.sat.gob.mx" xmlns:xd="http://www.w3.org/2000/09/xmldsig#">
    <s:Header/>
    <s:Body>
        <des:SolicitaDescarga>
            <des:solicitud FechaFinal="{{fecha_final}}" FechaInicial="{{fecha_inicial}}" {{rfc_tipo}}="{{rfc}}" RfcSolicitante="{{rfc}}" TipoSolicitud="{{solicitud}}">
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
            </des:solicitud>
        </des:SolicitaDescarga>
    </s:Body>
</s:Envelope>
BTMENV;

        $data['digest_value'] = $this->makeDigestValue($data);
        $data['signature_value'] = $this->signatureValue($data['digest_value'], $session->key_pem);
        $data['keyinfo_node'] = $this->keyInfoX509Node($session);

        return utils::injectInXmlTemplate($data, $body_template);
    }

    protected function makeDigestValue(array $data)
    {
        static $digest_template = <<< DIT
<des:SolicitaDescarga xmlns:des="http://DescargaMasivaTerceros.sat.gob.mx">
    <des:solicitud {{rfc_tipo}}="{{rfc}}" RfcSolicitante="{{rfc}}" FechaInicial="{{fecha_inicial}}" FechaFinal="{{fecha_final}}" TipoSolicitud="CFDI"></des:solicitud>
</des:SolicitaDescarga>
DIT;

        $digest = utils::injectInXmlTemplate($data, $digest_template);

        return base64_encode(sha1($digest, true));
    }

    /**
     * @param string $response
     *
     * @return SatWsDownloadResponse
     */
    private function parseResponse($response)
    {
        $data = utils::xml2Array($response);

        if (isset($data['Body']['Fault'])) {
            $code = $data['Body']['Fault']['faultcode'];
            $mensaje = $data['Body']['Fault']['faultstring']['_text'];
            throw new SatWsException($code . ': ' . $mensaje);
        }

        /** @var SatWsDownloadResponse $result */
        $result = new \stdClass();
        $solicitud = $data["Body"]["SolicitaDescargaResponse"]["SolicitaDescargaResult"]["_attributes"];
        $result->estado = intval($solicitud["CodEstatus"]);
        $result->mensaje = $solicitud["Mensaje"];

        // Casos en los que la solicitud es valida.
        //
        // 5000: Solicitud recibida con éxito
        // 5005: Solicitud duplicada
        $result->success = in_array($result->estado, [5000, 5005]);
        $result->id = null;
        $result->digest = null;
        if ($result->success) {
            $result->id = $solicitud["IdSolicitud"];
        }

        return $result;
    }

}