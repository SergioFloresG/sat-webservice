<?php
namespace MrGenis\Sat\Webservice;

/**
 * Class SatWsSession
 * @package MrGenis\Sat\Webservice
 *
 * @property-read string $key_pem
 * @property-read string $cer_pem
 * @property-read array  $cer_information
 * @property-read string $cer_rfc
 */
class SatWsSession
{

    public $pathname_cer;
    public $pathname_key;
    public $password;
    /** @var string contenido del archivo de certificado */
    public $cer;
    /** @var string contenido del archivo llave */
    public $key;

    public $session_succes;
    public $session_token;
    public $session_created;
    public $session_expires;

    private $__key_pem         = null;
    private $__cer_information = null;

    public function __construct($pathname_cer, $pathname_key, $password)
    {
        $this->pathname_cer = $pathname_cer;
        $this->pathname_key = $pathname_key;
        $this->password = $password;
        $this->init();
    }

    private function init()
    {
        if (!file_exists($this->pathname_cer)) {
            throw new \RuntimeException('Archivo de certificado no existe');
        }
        $this->cer = file_get_contents($this->pathname_cer);

        if (!file_exists($this->pathname_key)) {
            throw new \RuntimeException('Archivo de llave no existe');
        }
        $this->key = file_get_contents($this->pathname_key);
    }

    /**
     * Verifica si la session a expirado
     * @return bool
     */
    public function isExpired()
    {
        return (time() > $this->session_expires);
    }

    /**
     * @param string $key nombre de alguna proiedad
     *
     * @return mixed
     * @throws \Exception
     */
    public function __get($key)
    {
        if (method_exists($this, $key)) {
            return call_user_func([$this, $key]);
        }

        throw new \Exception('No se encuentra la propiedad.', null);
    }


    private function key_pem()
    {
        if (null === $this->__key_pem) {
            $tempfile = tempnam(sys_get_temp_dir(), 'satws-key.');
            $key = file_get_contents($this->pathname_key);
            file_put_contents($tempfile, $key);

            $command = sprintf('openssl pkcs8 -inform DER -in %s -passin pass:%s', $tempfile, $this->password);
            $output = shell_exec($command);
            unlink($tempfile);

            if ($output !== null) {
                $this->__key_pem = $output;
            } else {
                $this->__key_pem = '';
            }
        }

        return $this->__key_pem;
    }

    private function cer_pem()
    {
        $pem = chunk_split(base64_encode($this->cer), 64, "\n");
        $pem = "-----BEGIN CERTIFICATE-----\n{$pem}-----END CERTIFICATE-----\n";

        return $pem;
    }

    private function cer_information()
    {
        if (null === $this->__cer_information) {
            $data = openssl_x509_parse($this->cer_pem);

            $this->__cer_information = $data;
        }

        return $this->__cer_information;
    }

    private function cer_rfc()
    {
        return $this->cer_information['subject']['x500UniqueIdentifier'];
    }

    public function __wakeup()
    {
        $this->init();
    }

    public function __sleep()
    {
        return [
            'pathname_cer', 'pathname_key', 'password',
            'session_succes', 'session_token', 'session_created', 'session_expires'];
    }
}