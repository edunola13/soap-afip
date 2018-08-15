<?php
namespace Enola\Afip\Pdf;

use DateTime;

/**
 * Description of PdfVoucher
 *
 * @author Enola
 */
class PdfVoucher extends \Spipu\Html2Pdf\Html2Pdf{
    private $config = array();
    private $data = null;
    private $extraData = null;
    private $finished = false; //Determina si es la ultima pagina
    private $html = "";
    
    protected $parseIvaType= array(3 => 0, 4 => '10.5', 5 => '21', 6 => '27', 8 => '5', 9 => '2.5');
    private $lang = array();
    const LANG_EN = 2;
    
    function __construct($data, $extraData, $config) {
        parent::__construct('P', 'A4', 'es');
        $this->config = $config;
        $vconfig = array();
        //$vconfig["footer"] = false;
        //$vconfig["total_line"] = false;
        //$vconfig["header"] = false;
        //$vconfig["receiver"] = false;
        //$vconfig["footer"] = false;
        $this->config["VOUCHER_CONFIG"] = $vconfig;
        $this->data = $data;
        $this->extraData = $extraData;
        $this->finished = false;
        $cssfile = fopen(dirname(__FILE__) . "/voucher.css", "r");
        $css = fread($cssfile, filesize(dirname(__FILE__) . "/voucher.css"));
        fclose($cssfile);
        $this->html = "<style>" . $css . "</style>";
        if (array_key_exists("idiomaComprobante", $data) && $data["idiomaComprobante"] == $this::LANG_EN) {
            include(__DIR__.'/language/en.php');
        } else {
            include(__DIR__.'/language/es.php');
        }
        $this->lang = array_merge($this->lang, $lang);
    }
    
    private function lang($key) {
        if (array_key_exists($key,$this->lang)) {
            return $this->lang[$key];
        } else {
            return $key;
        }
    }
    
    /**
     * Genera la cabecera del comprobante
     * @param String $logo_path - Ubicación de la imágen del logo
     * @param String $title - Ej: ORIGINAL/DUPLICADO
     *
     * @author NeoComplexx Group S.A.
     */
    function addVoucherInformation($logo_path, $title) {
        if ($this->show_element("header")) {
            if ($title != "") {
                $this->html .= "<div class='border-div'>";
                $this->html .= "    <h3 class='center-text'>" . $title . "</h3>";
                $this->html .= "</div>";
                $this->html .= "<div class='border-div'>";
            }
            $type = $this->lang("FACTURA");
            $letter = $this->extraData["letra"]; //DEBE SER A, B o C
            $number = "<span style='font-weight: bold'>" . $this->lang("Punto de venta") . ":</span> " . str_pad($this->data["PtoVta"], 4, "0", STR_PAD_LEFT) . " <span style='font-weight: bold'>  " . $this->lang("Comp. Nro") . ":</span> " . str_pad($this->data["CbteDesde"], 8, "0", STR_PAD_LEFT);
            $tmp = DateTime::createFromFormat('Ymd',$this->data["CbteFch"]);
            $date = "<span style='font-weight: bold'>" . $this->lang("Fecha de emisi&oacute;n") . ":</span> " . date_format($tmp, $this->lang('d/m/Y'));
            $this->html .= "    <div class='letter'>";
            $this->html .= "        <p class='title'>$letter</p> ";
            $id_type = $this->data["CbteTipo"];
            $this->html .= "        <p class='type'>Cod. $id_type</p> ";
            $this->html .= "    </div>";
            $this->html .= "<table class='responsive-table table-header'>";
            $this->html .= "<tr><td style='width: 3%;'></td>";
            $this->html .= "<td style='width: 27%;'>";
            if (file_exists($logo_path)) {
                $this->html .= "<img class='logo' style='width: 100%' src='" . $logo_path . "' alt='logo'>";
            }
            $this->html .= "</td>";
            $this->html .= "<td class='right-text' style='width: 69%;'>";
            $this->html .= "    <span class='type_voucher header_margin' style='margin-bottom: 10px; font-size:30px'>$type</span><br>";
            $this->html .= "    <span class='header_margin'>$number</span><br>";
            $this->html .= "    <span class='header_margin'>$date</span>";
            if ($this->data["Concepto"] == 2 || $this->data["Concepto"] == 3) {
                $tmp = DateTime::createFromFormat('Ymd',$this->data["FchServDesde"]);
                $service_from = $this->lang("Per&iacute;odo") . ": " . date_format($tmp, $this->lang('d/m/Y'));
                $tmp = DateTime::createFromFormat('Ymd',$this->data["FchServHasta"]);
                $service_to = $this->lang("al") . " " . date_format($tmp, $this->lang('d/m/Y'));
                $tmp = DateTime::createFromFormat('Ymd',$this->data["FchVtoPago"]);
                $expiration = "- " . $this->lang("Vencimiento") . ": " . date_format($tmp, $this->lang('d/m/Y'));
                $this->html .= "<br>";
                $this->html .= "    <span class='header_margin'>$service_from</span>";
                $this->html .= "    <span class='header_margin'>$service_to</span>";
                $this->html .= "    <span class='header_margin'>$expiration</span>";
            }
            $this->html .= "</td>";
            $this->html .= "</tr>";
            $this->html .= "</table>";
            $this->html .= "<table class='responsive-table table-header'>";
            $this->html .= "<tr>";
            $this->html .= "<td style='width:50%;'><span style='font-weight:bold'>" . $this->lang("Raz&oacute;n social") . ":</span> " . strtoupper($this->config["TRADE_SOCIAL_REASON"]) . "</td>";
            $this->html .= "<td class='right-text' style='width:49%;'> <span style='font-weight:bold'>" . $this->lang("CUIT") . ":</span>  " . $this->config["TRADE_CUIT"] . "</td>";
            $this->html .= "</tr>";
            $this->html .= "<tr>";
            $this->html .= "<td style='width:50%;'><span style='font-weight:bold'>" . $this->lang("Domicilio comercial") . ":</span> " . strtoupper($this->config["TRADE_ADDRESS"]) . "</td>";
            $this->html .= "<td class='right-text' style='width:49%;'><span style='font-weight:bold'>" . $this->lang("Ingresos Brutos") . ":</span>  " . $this->config["TRADE_CUIT"] . "</td>";
            $this->html .= "</tr>";
            $this->html .= "<tr>";
            $this->html .= "<td style='width:50%;'><span style='font-weight:bold'>" . $this->lang("Condici&oacute;n frente al IVA") . ":</span>  " . strtoupper($this->config["TRADE_TAX_CONDITION"]) . "</td>";
            $tmp = DateTime::createFromFormat('d/m/Y',$this->config["TRADE_INIT_ACTIVITY"]);
            $this->html .= "<td class='right-text' style='width:49%;'> <span style='font-weight:bold'>" . $this->lang("Fecha de inicio de actividades") . ":</span> " . date_format($tmp, $this->lang('d/m/Y')) . "</td>";
            $this->html .= "</tr>";
            $this->html .= "</table>";
            $this->html .= "</div>";
        }
    }
    
    /**
     * Genera la información del receptor (cliente)
     *
     * @author: NeoComplexx Group S.A.
     */
    function addReceiverInformation() {
        if ($this->show_element("receiver")) {
            $this->html .= "<div class='border-div'>";
            $this->html .= "<table class='responsive-table table-header'>";
            $this->html .= "<tr>";
            $text = "<span style='font-weight:bold'>" . $this->lang($this->extraData["docTipoDetalle"]) . ":</span> " . $this->data["DocNro"];
            $this->html .= "<td style='width:50%;'>" . $text . "</td>";
            $text = "<span style='font-weight:bold'>" . $this->lang("Apellido y Nombre / Raz&oacute;n Social") . ":</span> " . strtoupper($this->extraData["nombreCliente"]);
            $this->html .= "<td class='right-text' style='width:49%;'>" . $text . "</td>";
            $this->html .= "</tr>";
            $this->html .= "<tr>";
            $text = "<span style='font-weight:bold'>" .$this->lang("Condici&oacute;n frente al IVA") . ":</span> " . $this->lang($this->extraData["tipoResponsable"]);
            $this->html .= "<td style='width:50%;'>" . $text . "</td>";
            $text = "<span style='font-weight:bold'>". $this->lang("Domicilio") . ":</span> " . $this->extraData["domicilioCliente"];
            $this->html .= "<td class='right-text' style='width:49%;'>" . $text . "</td>";
            $this->html .= "</tr>";
            $this->html .= "<tr>";
            $text = "<span style='font-weight:bold'>". $this->lang("Condici&oacute;nes de venta") . ": </span>" . $this->lang($this->extraData["CondicionVenta"]);
            $this->html .= "<td style='width:10%;'>" . $text . "</td>";
            $this->html .= "</tr>";
            $this->html .= "</table>";
            $this->html .= "</div>";
        }
    }
    
    /**
     * Genera la tabla con los articulos del comprobante
     *
     * @author NeoComplexx Group S.A.
     */
    function fill() {
        $this->html .= "<table class='responsive-table table-article'>";
        if (strtoupper($this->extraData["letra"]) === 'A') {
            $this->fill_A();
        } else {
            $this->fill_B();
        }
        $this->html .= "</table>";
        $this->finished = true;
    }
    
    /**
     * Imprime el detalle para comprobantes tipo A
     * 
     * @author NeoComplexx Group S.A.
     */
    function fill_A() {
        $this->html .= "<tr>";
        $this->html .= "<th class='center-text' style='width=13%;'>" . $this->lang("C&oacute;digo") . "</th>";
        $this->html .= "<th style='width=23%;'>" . $this->lang("Producto / Servicio") . "</th>";
        $this->html .= "<th class='right-text' style='width=10%;'>" . $this->lang("Cantidad") . "</th>";
        $this->html .= "<th style='width=10%;'>" . $this->lang("U. Medida") . "</th>";
        $this->html .= "<th class='right-text' style='width=10%;'>" . $this->lang("Precio unit.") . "</th>";
//        $this->html .= "<th class='right-text' style='width=8%;'>" . $this->lang("% Bonif") . "</th>";
//        $this->html .= "<th class='right-text' style='width=10%;'>" . $this->lang("Imp. Bonif.") . "</th>";
        $this->html .= "<th class='right-text' style='width=18%;'>" . $this->lang("Variacion") . "</th>";
        $this->html .= "<th class='right-text' style='width=6%;'>" . $this->lang("IVA") . "</th>";
        $this->html .= "<th class='right-text' style='width=10%;'>" . $this->lang("Subtotal") . "</th>";
        $this->html .= "</tr>";
        foreach ($this->extraData["items"] as $item) {
            $this->html .= "<tr>";
            if (isset($this->config["TYPE_CODE"]) && $this->config["TYPE_CODE"] == 'scanner') {
                $this->html .= "<td class='center-text' style='width=13%;'>" . $item["scanner"] . "</td>";
            } else {
                $this->html .= "<td class='center-text' style='width=13%;'>" . $item["codigo"] . "</td>";
            }
            $this->html .= "<td style='width=23%;'>" . $item["descripcion"] . "</td>";
            $this->html .= "<td class='right-text' style='width=10%;'>" . number_format($item["cantidad"], 3) . "</td>";
            $this->html .= "<td style='width=10%;'>" . $item["unidadMedida"] . "</td>";
            $this->html .= "<td class='right-text' style='width=10%;'>" . number_format($item["precioUnitario"], 2) . "</td>";
            /*$this->html .= "<td class='right-text' style='width=8%;'>" . number_format($item["porcBonif"], 2) . "</td>";
            $this->html .= "<td class='right-text' style='width=10%;'>" . number_format($item["impBonif"], 2) . "</td>";*/
            $this->html .= "<td class='right-text' style='width=18%;'>" . number_format($item["variacion"], 2) . "</td>";
            $this->html .= "<td class='right-text' style='width=6%;'>" . (is_numeric($item["Alic"]) ? number_format($item["Alic"], 2) . '%' : '-') . "</td>";
            $this->html .= "<td class='right-text' style='width=10%;'>" . number_format($item["importeItem"], 2) . "</td>";
            $this->html .= "</tr>";
        }
    }
    
    /**
     * Imprime el detalle para comprobantes tipo B
     * 
     * @author NeoComplexx Group S.A.
     */
    function fill_B() {
        $this->html .= "<tr>";
        $this->html .= "<th class='center-text' style='width=13%;'>" . $this->lang("C&oacute;digo") . "</th>";
        $this->html .= "<th style='width=27%;'>" . $this->lang("Producto / Servicio") . "</th>";
        $this->html .= "<th class='right-text' style='width=10%;'>" . $this->lang("Cantidad") . "</th>";
        $this->html .= "<th style='width=10%;'>" . $this->lang("U. Medida") . "</th>";
        $this->html .= "<th class='right-text' style='width=10%;'>" . $this->lang("Precio unit.") . "</th>";
//        $this->html .= "<th class='right-text' style='width=10%;'>" . $this->lang("% Bonif") . "</th>";
//        $this->html .= "<th class='right-text' style='width=10%;'>" . $this->lang("Imp. Bonif.") . "</th>";
        $this->html .= "<th class='right-text' style='width=20%;'>" . $this->lang("Variacion") . "</th>";
        $this->html .= "<th class='right-text' style='width=10%;'>" . $this->lang("Subtotal") . "</th>";
        $this->html .= "</tr>";
        foreach ($this->extraData["items"] as $item) {
            $this->html .= "<tr>";
            if (isset($this->config["TYPE_CODE"]) && $this->config["TYPE_CODE"] == 'scanner') {
                $this->html .= "<td class='center-text' style='width=13%;'>" . $item["scanner"] . "</td>";
            } else {
                $this->html .= "<td class='center-text' style='width=13%;'>" . $item["codigo"] . "</td>";
            }
            $this->html .= "<td style='width=27%;'>" . $item["descripcion"] . "</td>";
            $this->html .= "<td class='right-text' style='width=10%;'>" . number_format($item["cantidad"], 3) . "</td>";
            $this->html .= "<td style='width=10%;'>" . $this->lang($item["unidadMedida"]) . "</td>";
            $this->html .= "<td class='right-text' style='width=10%;'>" . number_format($item["precioUnitario"], 2) . "</td>";
            /*$this->html .= "<td class='right-text' style='width=10%;'>" . number_format($item["porcBonif"], 2) . "</td>";
            $this->html .= "<td class='right-text' style='width=10%;'>" . number_format($item["impBonif"], 2) . "</td>";*/
            $this->html .= "<td class='right-text' style='width=20%;'>" . number_format($item["variacion"], 2) . "</td>";
            $this->html .= "<td class='right-text' style='width=10%;'>" . number_format($item["importeItem"], 2) . "</td>";
            $this->html .= "</tr>";
        }
    }
    
    /**
     * Imprime la linea de totales
     *
     * @author NeoComplexx Group S.A.
     */
    function total_line() {
        if ($this->show_element("total_line")) {
            $this->html .= "<div class='border-div'>";
            if (strtoupper($this->extraData["letra"]) === 'A') {
                $this->total_line_A();
            } else {
                $this->total_line_B();
            }
            $this->html .= "</div>";
        }
    }
    
    /**
     * Imprime la linea de totales para comprobantes con letra A
     *
     * @author NeoComplexx Group S.A.
     */
    private function total_line_A() {
        $this->html .= '    <table class="responsive-table">';
        $this->html .= '        <tr>';
        $this->html .= '            <td style="width: 60%;">';
        $this->html .= $this->othertaxes();
        $this->html .= '            </td>';
        $this->html .= '            <td style="width: 40%;">';
        $this->html .= $this->ivas();
        $this->html .= '            </td>';
        $this->html .= '        </tr>';
        $this->html .= '    </table>';
    }
    
    /**
     * Imprime la linea de totales para comprobantes con letra B
     *
     * @author: NeoComplexx Group S.A.
     */
    private function total_line_B() {
        $this->html .= '    <table class="responsive-table">';
        $this->html .= '        <tr>';
        $this->html .= '		<td class="right-text" style="width: 75%; font-weight:bold">' . $this->lang("Subtotal") . ': '. $this->lang($this->data["MonId"]) .'</td>';
        $text = number_format((float) round($this->data["ImpTotal"], 2), 2, '.', '');
        $this->html .= '		<td class="right-text" style="width: 25%;">' . $text . '</td>';
        $this->html .= '        </tr>';
        $this->html .= '        <tr>';
        $this->html .= '		<td class="right-text" style="width: 75%; font-weight:bold">' . $this->lang("Importe otros tributos") . ': '. $this->lang($this->data["MonId"]) .'</td>';
        $text = number_format((float) round($this->data["ImpTrib"], 2), 2, '.', '');
        $this->html .= '		<td class="right-text" style="width: 25%;">' . $text . '</td>';
        $this->html .= '        </tr>';
        $this->html .= '        <tr>';
        $this->html .= '		<td class="right-text" style="width: 75%;font-weight:bold">' . $this->lang("Importe total") . ': '. $this->lang($this->data["MonId"]) .'</td>';
        $text = number_format((float) round($this->data["ImpTotal"], 2), 2, '.', '');
        $this->html .= '		<td class="right-text" style="width: 25%;">' . $text . '</td>';
        $this->html .= '        </tr>';
        $this->html .= '    </table>';
    }
    
    /**
     * Retorna la tabla de otros tributos
     *
     * @return string
     *
     * @author NeoComplexx Group S.A.
     */
    private function othertaxes() {
        $str = "";
        if (isset($this->data['Tributos']) && count($this->data['Tributos']) > 0) {
            $str .= '    <table class="responsive-table table-article">';
            //Title
            $str .= '        <tr>';
            $str .= '            <th class="center-text" colspan=2 style="width=240px;">Otros tributos</th>';
            $str .= '        </tr>';
            $str .= '        <tr>';
            $str .= '            <th class="center-text" style="width=200px;">' . $this->lang("Descripci&oacute;n") . '</th>';
            $str .= '            <th class="center-text" style="width=40px;">' . $this->lang("Importe") . '</th>';
            $str .= '        </tr>';
            foreach ($this->data['Tributos'] as $tax) {
                $str .= '        <tr>';
                $str .= '            <td class="left-text" style="width=200px;">' . $tax["Desc"] . '</td>';
                $str .= '            <td class="right-text" style="width=40px;">' . $tax["Importe"] . '</td>';
                $str .= '        </tr>';
            }
            //Footer
            $str .= '        <tr>';
            $str .= '            <td class="right-text" style="width=200px;">' . $this->lang("Importe otros tributos") . ': '. $this->lang($this->data["MonId"]) .'</td>';
            $total = number_format((float) round($this->data["ImpTrib"], 2), 2, '.', '');
            $str .= '            <td class="right-text" style="width=40px;">' . $total . '</td>';
            $str .= '        </tr>';
            $str .= '    </table>';
        }
        return $str;
    }
    
    /**
     * Retorna la tabla de ivas y totales
     *
     * @return string
     *
     * @author NeoComplexx Group S.A.
     */
    private function ivas() {
        $str = '    <table class="responsive-table">';
        //Detail
        $str .= '        <tr>';
        $str .= '            <td class="right-text" style="width=200px;">' . $this->lang("Importe neto gravado") . ': '. $this->lang($this->data["MonId"]) .'</td>';
        $importeGravado = number_format((float) round($this->data["ImpNeto"], 2), 2, '.', '');
        $str .= '            <td class="right-text" style="width=70px;">' . $importeGravado . '</td>';
        $str .= '        </tr>';
        foreach ($this->data["Iva"] as $iva) {
            $id = $iva["Id"];
            $detIva= $this->parseIvaType[$id];
            $descripcion = "IVA $detIva%: " . $this->lang($this->data["MonId"]);
            $importe = number_format((float) round($iva["Importe"], 2), 2, '.', '');
            $str .= '        <tr>';
            $str .= '            <td class="right-text" style="width=200px;">' . $descripcion . '</td>';
            $str .= '            <td class="right-text" style="width=70px;">' . $importe . '</td>';
            $str .= '        </tr>';
        }
        //Footer
        $str .= '        <tr>';
        $str .= '            <td class="right-text" style="width=200px;">' . $this->lang("Importe otros tributos") . ': '. $this->lang($this->data["MonId"]) .'</td>';
        $importeOtrosTributos = number_format((float) round($this->data["ImpTrib"], 2), 2, '.', '');
        $str .= '            <td class="right-text" style="width=70px;">' . $importeOtrosTributos . '</td>';
        $str .= '        </tr>';
        $str .= '        <tr>';
        $str .= '            <td class="right-text" style="width=200px;">' . $this->lang("Importe total") . ': '. $this->lang($this->data["MonId"]) .'</td>';
        $importeTotal = number_format((float) round($this->data["ImpTotal"], 2), 2, '.', '');
        $str .= '            <td class="right-text" style="width=70px;">' . $importeTotal . '</td>';
        $str .= '        </tr>';
        $str .= '    </table>';
        return $str;
    }   
    
    function extra_line() {
        $extra = $this->config["VOUCHER_OBSERVATION"];
        if ($extra != "") {
            $this->html .= "<div class='border-div'>";
            $this->html .= '    <table class="responsive-table">';
            $this->html .= "        <tr><td class='center-text'style='width: 100%;'>$extra</td></tr>";
            $this->html .= '    </table>';
            $this->html .= "</div>";
        }
    }
    
    /**
     * Imprime el pie de pagina
     *
     * @author NeoComplexx Group S.A.
     */
    function footer() {
        if ($this->show_element("footer")) {
            $this->html .= '<page_footer>';
            $this->total_line();
            $this->extra_line();
            $this->html .= '    <table class="responsive-table page_footer">';
            if ($this->extraData["cae"] != 0) {
                $text_left = $this->lang("Comprobante Autorizado");
                $text_1 = $this->lang("CAE") .": ";
                $text_2 = $this->extraData["cae"];
                $text_3 = $this->lang("Fecha Vto. CAE") .": ";
            
                $tmp = DateTime::createFromFormat('Y-m-d',$this->extraData["fechaVencimientoCAE"]);
                $text_4 = date_format($tmp, $this->lang('d/m/Y'));
                $quotation = number_format((float) round($this->data["MonCotiz"], 2), 2, '.', '');
                $text_5 = $this->lang("Moneda") . ": " . $this->lang($this->data["MonId"]) . " | " . $this->lang("Cotizaci&oacute;n") . ": " . $quotation;
            } else {
                $text_left = $this->lang("Documento no v&aacute;lido como factura");
                $text_1 = "&nbsp;";
                $text_2 = "&nbsp;";
                $text_3 = "&nbsp;";
                $text_4 = "&nbsp;";
                $text_5 = "&nbsp;";
            }
            $this->html .= '        <tr>';
            $this->html .= '		<td class="" style="width: 30%;">' . $text_left . "</td>";
            $this->html .= '		<td class="center-text" style="width: 40%;">';
            $this->html .= '                ' . $this->lang("Pag.") . ' [[page_cu]]/[[page_nb]]';
            $this->html .= '		</td>';
            $this->html .= '		<td class="right-text" style="width: 15%;">' . $text_1 . "</td>";
            $this->html .= '		<td class="left-text" style="width: 15%;">' . $text_2 . "</td>";
            $this->html .= '        </tr>';
            $this->html .= '        <tr>';
            $this->html .= '		<td class="" style="width: 30%;">' . $text_5 . "</td>";
            $this->html .= '		<td class="center-text" style="width: 40%;">&nbsp;</td>';
            $this->html .= '		<td class="right-text" style="width: 15%;">' . $text_3 . "</td>";
            $this->html .= '		<td class="left-text" style="width: 15%;">' . $text_4 . "</td>";
            $this->html .= '        </tr>';
            $this->html .= '    </table>';
            if ($this->extraData["cae"] != 0) {
                //BARCODE
                $cuit = str_replace("-", "", $this->config["TRADE_CUIT"]);
                $pos = str_pad($this->data["PtoVta"], 4, "0", STR_PAD_LEFT);
                $type = str_pad($this->data["CbteTipo"], 2, "0", STR_PAD_LEFT);
                $cae = $this->extraData["cae"];
                $fecha = str_replace("-", "", $this->extraData["fechaVencimientoCAE"]);
                $barcode_number = $cuit . $type . $pos . $cae . $fecha;
                $this->html .= '<barcode type="I25+" value="' . $barcode_number . '" label="label" style="width:20cm; height:7mm; font-size: 7px"></barcode>';
            }
            $this->html .= '</page_footer>';
        }
    }
    
    /**
     * Determina si mostrar o no una parte del comprobante
     * @param element TAG del elemento a controlar
     * 
     * @author NeoComplexx Group S.A.
     */
    private function show_element($element) {
        if (array_key_exists("VOUCHER_CONFIG", $this->config) && array_key_exists($element, $this->config["VOUCHER_CONFIG"])) {
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * Genera un comprobante de AFIP con su correspondiente original/duplicado
     *
     * @param type $logo_path Ubicación de la imágen del logo
     * 
     * @author NeoComplexx Group S.A.
     */
    function emitirPDF($logo_path) {
        //ORIGINAL
        $this->html .= "<page>";
        $this->addVoucherInformation($logo_path, $this->lang("ORIGINAL"));
        $this->addReceiverInformation();
        $this->fill();
        $this->footer();
        $this->html .= "</page>";
        $this->html .= "<page>";
        //DUPLICADO
        $this->addVoucherInformation($logo_path, $this->lang("DUPLICADO"));
        $this->addReceiverInformation();
        $this->fill();
        $this->footer();
        $this->html .= "</page>";
        $this->WriteHTML($this->html);
    }
}
