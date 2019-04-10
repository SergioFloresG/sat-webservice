<?php
namespace MrGenis\Sat\Webservice;


class SatWsUtils
{

    /**
     * Genera un uuid de version 4
     * @return string uuid v4
     */
    static function uuidV4()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * @param integer $body_length
     * @param string  $action
     * @param string  $token
     *
     * @return array
     */
    static function headers($body_length, $action, $token = '')
    {
        return [
            'Content-type: text/xml;charset="utf-8"',
            'Accept: text/xml',
            'Cache-Control: no-cache',
            !empty($token) ? sprintf('Authorization: WRAP access_token="%s"', $token) : '',
            'SOAPAction: ' . $action,
            'Content-length: ' . $body_length
        ];
    }

    /**
     * @param array  $data
     * @param string $template
     *
     * @return string
     */
    static function injectInXmlTemplate(array $data, $template)
    {
        $template = preg_replace('/\n\s*/', '', $template);
        $find = preg_match_all('/\{\{([^}]+)\}\}/i', $template, $matches);
        if ($find) {
            $matches = $matches[1];
            $matches = array_unique($matches);
            foreach ($matches as $match) {
                $pattern = '/\{\{' . $match . '\}\}/';
                if (!array_key_exists($match, $data)) continue;
                $template = preg_replace($pattern, $data[$match], $template);
            }
        }

        return $template;
    }

    /**
     * @param string $xml
     *
     * @return array
     */
    static function xml2Array($xml)
    {
        return XmlToArray::convert($xml);
    }

    /**
     * @param string $url
     * @param string $action
     * @param string $body
     * @param string $token
     *
     * @return false|resource
     */
    static function curl_init($url, $action, $body, $token = '')
    {
        $headers = static::headers(strlen($body), $action, $token);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT_MS, 50000);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        return $curl;
    }

}