<?php
namespace MrGenis\Sat\Webservice\Models;

final class  SatWsConstants
{
    /** @var string indica la descarga de facturas recibidas */
    const DOWNLOAD_TYPE_RECEIVED = 'recibidas';
    /** @var string indica la descarga de facturas emitidas */
    const DOWNLOAD_TYPE_EMITED = 'emitidas';

    /** @var array arrelgo de los tipos de descargas disponibles */
    const DOWNLOAD_TYPES = [
        self::DOWNLOAD_TYPE_EMITED,
        self::DOWNLOAD_TYPE_RECEIVED];


    /** @var string indica que la descara obtiene un paquete de cfdi */
    const DOWNLOAD_REQUEST_CFDI = 'CFDI';
    /** @var string indica que la descarga obtiene un paquete de meta datos. */
    const DOWNLOAD_REQUEST_META = 'METADATA';

    const DOWNLOAD_REQUESTS = [
        self::DOWNLOAD_REQUEST_CFDI,
        self::DOWNLOAD_REQUEST_META];
}