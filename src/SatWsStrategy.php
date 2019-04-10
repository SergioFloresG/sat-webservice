<?php
namespace MrGenis\Sat\Webservice;

use MrGenis\Sat\Webservice\Exception\SatWsException;
use MrGenis\Sat\Webservice\Services\SatWsDownloadRequester;
use MrGenis\Sat\Webservice\Services\SatWsLoginRequester;
use MrGenis\Sat\Webservice\Services\SatWsPackageRequester;
use MrGenis\Sat\Webservice\Services\SatWsVerificationRequester;

/**
 * Class SatWsStrategy
 * @package MrGenis\Sat\Webservice
 */
class SatWsStrategy
{
    /** @var SatWsLoginRequester */
    private $login_requester;
    /** @var SatWsDownloadRequester */
    private $download_requester;
    /** @var SatWsVerificationRequester */
    private $verification_requester;
    /** @var SatWsPackageRequester */
    private $package_requester;

    public function __construct()
    {
        $this->login_requester = new SatWsLoginRequester();
        $this->download_requester = new SatWsDownloadRequester();
        $this->verification_requester = new SatWsVerificationRequester();
        $this->package_requester = new SatWsPackageRequester();
    }

    /**
     * @param string $cer archivo del certificado (e.Firma)
     * @param string $key archivo de la llave (e.Firma)
     * @param string $password
     *
     * @return SatWsSession
     * @throws SatWsException
     */
    public function obtainSession($cer, $key, $password)
    {
        $session = new SatWsSession($cer, $key, $password);
        $login = $this->login_requester->login($session);
        if ($login->success) {
            return $session;
        }

        return $session;
    }

    /**
     * @param SatWsSession $session
     * @param string       $fecha_inicial <em>Y-m-d H:i:s</em>
     * @param string       $fecha_final <em>Y-m-d H:i:s</em>
     * @param string       $tipo Debe de ser <em>emitidas</em> o <em>recibidas</em>
     * @param string       $paquete Define el tipo de paquete a solicitar, <u><em>CFDI</em></u> o <em>METADATA</em>
     *
     * @return Models\SatWsDownloadResponse
     * @throws SatWsException
     */
    public function requestDownload(SatWsSession $session, $fecha_inicial, $fecha_final, $tipo, $paquete = 'CFDI')
    {
        return $this->download_requester->request($session, $fecha_inicial, $fecha_final, $tipo, $paquete);
    }

    /**
     * @param SatWsSession $session
     * @param string       $solicitud_id
     *
     * @return Models\SatWsVerificationResponse
     * @throws SatWsException
     */
    public function verifyDownload(SatWsSession $session, string $solicitud_id)
    {
        return $this->verification_requester->request($session, $solicitud_id);
    }

    /**
     * @param SatWsSession $session
     * @param array        $paquetes_ids Coleccion de identificadores de los paquetes a descargar.
     * @param string       $destination_directory Ruta absoluta del directorio destino de la descarga de paquetes.
     *
     * @return string[]    Direccion a los paquetes descargados;
     */
    public function downloadPackages(SatWsSession $session, array $paquetes_ids, $destination_directory)
    {
        $packages_filenames = [];
        if (!file_exists($destination_directory)) {
            throw new SatWsException('Destination directory does not exist.');
        } elseif (!is_dir($destination_directory)) {
            throw new SatWsException('Destination path is does not a directory.');
        } elseif (is_writable($destination_directory)) {
            throw new SatWsException('It is not possible to write to the destination directory.');
        }

        foreach ($paquetes_ids as $paquete_id) {
            $paquete_body_base64 = $this->package_requester->request($session, $paquete_id);
            $package_filepath = $destination_directory . DIRECTORY_SEPARATOR . $paquete_id . '.zip';

            $isWrited = @file_put_contents($package_filepath, base64_decode($paquete_body_base64));

            if ($isWrited) {
                $packages_filenames[] = $package_filepath;
            }
        }

        return $packages_filenames;
    }

}