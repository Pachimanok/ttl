<?php

namespace App\Http\Controllers;

use App\Models\asign;
use App\Models\position;
use App\Models\pruebasModel;
use App\Models\truck;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LDAP\Result;
use Mockery\Undefined;

// wget -O /dev/null "https://rail.com.ar/api/servicioSatelital"
// tiempo */2 * * * *

class ServiceSatelital extends Controller
{
    public function servicePrueba()
    {
        return env('APP_URL') . env('APP_NAME');
    }
    public function serviceSatelital()
    {
        $todosMisCamiones = DB::table('trucks')
            ->join('asign', 'trucks.domain', '=', 'asign.truck')
            ->join('cntr', 'cntr.cntr_number', '=', 'asign.cntr_number')
            ->join('carga', 'carga.booking', '=', 'cntr.booking')
            ->join('aduanas', 'aduanas.description', '=', 'carga.custom_place')
            ->join('customer_load_places', 'customer_load_places.description', '=', 'carga.load_place')
            ->join('customer_unload_places', 'customer_unload_places.description', '=', 'carga.unload_place')
            ->select('cntr.id_cntr as IdTrip', 'carga.id as idCarga', 'trucks.id', 'trucks.id_satelital', 'trucks.domain', 'customer_load_places.description as LugarCarga', 'customer_load_places.latitud as CargaLat', 'customer_load_places.longitud as CargaLng', 'aduanas.description as LugarAduana', 'aduanas.lat as aduanaLat', 'aduanas.lon as aduanaLon', 'customer_unload_places.description as lugarDescarga', 'customer_unload_places.latitud as descargaLat', 'customer_unload_places.longitud as descargaLon')
            ->where('cntr.main_status', '!=', 'TERMINADA')
            ->get();

        $chek = new pruebasModel();
        $chek->contenido = '1. Consulto las patentes del Camion';
        $chek->save();

        foreach ($todosMisCamiones as $camion) {

           
            $client = new Client();
            $headers = [
                'Content-Type' => 'application/json'
            ];

            // TEST: E6HW19 - PRODUCCION: C2QC20

            if(env('APP_ENV') === 'production'){
                $body = '{
                    "patentes":["' . $camion->domain . '"],
                    "cercania":true,
                    "domicilio":false,
                    "apiCode":"C2QC20",
                    "phone":"2612128105"
                    }';
            }else{
                $body = '{
                    "patentes":["' . $camion->domain . '"],
                    "cercania":true,
                    "domicilio":false,
                    "apiCode":"E6HW19",
                    "phone":"2612128105"
                    }';
            }
           
            $request = new Psr7Request('GET', 'https://app.akercontrol.com/ws/v2/servicios', $headers, $body);
            $res = $client->sendAsync($request)->wait();
            $respuesta = $res->getBody();
            $r = json_decode($respuesta, true);
            $keys = array($r);

            if (array_key_exists('data', $r)) {

              

                $datos = $keys[0]['data'][$camion->domain];

                $posicionLat = $datos['ult_latitud'];
                $posicionLon = $datos['ult_longitud'];

                $positionDB = new position();
                $positionDB->dominio = $camion->domain;
                $positionDB->lat = $posicionLat;
                $positionDB->lng = $posicionLon;
                $positionDB->save();

                $chek = new pruebasModel();
                $chek->contenido = $camion->domain . '2.a. RESPUESTA Se encuentra en Lat: ' . $posicionLat . ' - lon: ' . $posicionLon;
                $chek->save();

                $IdTrip = $camion->IdTrip;
            

                $Radio = 6371e3; // metres
                $φ1 = $posicionLat * pi() / 180; // φ, λ in radians
                $φ2 = $camion->CargaLat * pi() / 180;
                $φ3 = $camion->aduanaLat * pi() / 180;
                $φ4 = $camion->descargaLat * pi() / 180;

                $Δφ = ($posicionLat - $camion->CargaLat) * pi() / 180;
                $Δφ2 = ($posicionLat - $camion->aduanaLat) * pi() / 180;
                $Δφ3 = ($posicionLat - $camion->descargaLat) * pi() / 180;

                $Δλ = ($posicionLon - $camion->CargaLng) * pi() / 180;
                $Δλ2 = ($posicionLon - $camion->aduanaLon) * pi() / 180;
                $Δλ3 = ($posicionLon - $camion->descargaLon) * pi() / 180;

                $a = sin($Δφ / 2) * sin($Δφ / 2) + cos($φ1) * cos($φ2) * sin($Δλ / 2) * sin($Δλ / 2);
                $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                $d = $Radio * $c; // in metres

                $a2 = sin($Δφ2 / 2) * sin($Δφ2 / 2) + cos($φ1) * cos($φ3) * sin($Δλ2 / 2) * sin($Δλ2 / 2);
                $c2 = 2 * atan2(sqrt($a2), sqrt(1 - $a2));
                $d2 = $Radio * $c2; // in metres

                $a3 = sin($Δφ3 / 2) * sin($Δφ3 / 2) + cos($φ1) * cos($φ4) * sin($Δλ3 / 2) * sin($Δλ3 / 2);
                $c3 = 2 * atan2(sqrt($a3), sqrt(1 - $a3));
                $d3 = $Radio * $c3; // in metres */

               



                if ($d <= 200) { // lugar de Carga

                

                    $clientCarga = new Client();
                    $requestCarga = new Psr7Request('GET', env('APP_URL') . '/api/accionLugarDeCarga/' . $IdTrip);
                    $resCarga = $clientCarga->sendAsync($requestCarga)->wait();
                }

                if ($d2 <= 200) { // lugar de aduana


                   
                    $clientAduana = new Client();
                    $requestAduana = new Psr7Request('GET', env('APP_URL') . '/api/accionLugarAduana/' . $IdTrip);
                    $resAduana = $clientAduana->sendAsync($requestAduana)->wait();
                }
                if ($d3 <= 200) { // lugar de descarga


                   

                    $clientDescarga = new Client();
                    $requestDescarga = new Psr7Request('GET', env('APP_URL') . '/api/accionLugarDescarga/' . $IdTrip);
                    $resDescarga = $clientDescarga->sendAsync($requestDescarga)->wait();
                }
                
                

                // Agregar punntos Criticos Globales.
            }
        }
    }
    public function flota()
    {

        $curl = curl_init();

        // TEST: E6HW19 - PRODUCCION: C2QC20
        if (env('APP_ENV') === 'production') {
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://app.akercontrol.com/ws/flota/2612128105/C2QC20',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ));
        } else {
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://app.akercontrol.com/ws/flota/2612128105/E6HW19',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ));
        }

        

        $response = curl_exec($curl);
        $json = json_decode($response);
        $datos = $json->data;

        $camiones = [];

        foreach ($datos as $dato) {

            if (!empty($dato->patente)) { // Verificar si 'patente' no es nulo
                $todosMisCamiones = DB::table('trucks')
                    ->join('transports', 'trucks.transport_id', '=', 'transports.id')
                    ->where('trucks.domain', '=', $dato->patente)
                    ->get();

                if ($todosMisCamiones->isNotEmpty()) { // Verificar si se encontraron camiones
                    $camion = $todosMisCamiones->first();

                    $trcuk['model'] = $camion->model;
                    $trcuk['domain'] = $camion->domain;
                    $trcuk['year'] = $camion->year;
                    $trcuk['vto_poliza'] = $camion->vto_poliza;
                    $trcuk['razon_social'] = $camion->razon_social;
                    $trcuk['logo'] = $camion->logo;
                    $trcuk['vto_permiso'] = $camion->vto_permiso;
                    $trcuk['titulo'] = $dato->nombre;
                    $trcuk['ult_latitud'] = $dato->ult_latitud;
                    $trcuk['ult_longitud'] = $dato->ult_longitud;
                    $trcuk['ult_velocidad'] = $dato->ult_velocidad;
                    $trcuk['ult_fecha'] = $dato->ult_fecha;
                    $trcuk['ult_reporte'] = $dato->ult_reporte;
                    $trcuk['ult_direccion'] = $dato->ult_direccion;
                    $truck['direccion'] = $dato->ult_direccion;

                    array_push($camiones, $trcuk);
                }
            }
           
        
        
    }
        return $camiones;
    }
}
