<?php
namespace MrGenis\Sat\Webservice\Models;

/**
 * Interface SatWsVerificationResponse
 * @package App\Facturify\Strategies\Sat\Webservice\Models
 *
 * @property integer      $numero_cfdi Cantidad de archivos CFDI en total
 * @property array[]|null $paquetes Identificadores de los paquetes disponibles para su descarga.
 * @property integer      $paquete_estado Clave para conocer el estado de los paquedes a descargar.<br><br>
 *
 * 1. Aceptada<br>
 * 2. En proceso<br>
 * 3. Terminada<br>
 * 4. Error<br>
 * 5. Rechazada<br>
 * 6. Vencida<br>
 *
 * @property string       $mensaje Mensaje sobre la solicitud.
 * @property integer      $estado Contiene el código de estado de la solicitud de descarga.<br><br>
 *
 * <p><b>5000</b>. Solicitud recibida con éxito</p>
 * <p><b>5002</b>. Se agotó las solicitudes de por vida. Para el caso de descarga de tipo CFDI, se tiene un límite
 *     máximo para solicitudes con los mismos parámetros (Fecha Inicial, Fecha Final, RfcEmisor, RfcReceptor)</p>
 * <p><b>5003</b>. Tope máximo. Indica que en base a los parámetros de consulta se está superando el tope máximo de
 *     CFDI o Metadata, por solicitud de descarga masiva</p>
 * <p><b>5004</b>. No se encontró la información. Indica que la solicitud de descarga que se está verificando no
 *     generó paquetes por falta de información.</p>
 * <p><b>5005</b>. Solicitud duplicada. En caso de que exista una solicitud vigente con los mismos parámetros (Fecha
 *     Inicial, Fecha Final, RfcEmisor, RfcReceptor, TipoSolicitud), no se permitirá generar una nueva solicitud.</p>
 * <p><b>404</b>. Error no controlado</p>
 *
 */
interface SatWsVerificationResponse
{
}