<?php
namespace MrGenis\Sat\Webservice\Models;

/**
 * Interface SatWsDownloadResponse
 * @package MrGenis\Sat\Webservice\Models
 *
 * @property integer     $estado Indica la clave del resultado de la solicitud.
 * @property string      $mensaje Mensaje sobre la solicitud.
 * @property boolean     $success indica si la solicitud de descarga es valida.<br>
 * <b>TRUE</b>: cuando el estado es <em>5000</em> o <em>5005</em>, <b>FALSE</b> en otro caso.
 * @property string|null $id uuid de la solicitud de descarga.
 * @property string|null $digest firma de la solicitud.
 */
interface SatWsDownloadResponse
{
}