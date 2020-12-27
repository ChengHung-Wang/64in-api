<?php

namespace App\Http\Controllers;

use App\Models\Countrys;
use App\Models\Covid19_datas;
use App\Models\Islands;
use App\Models\Provinces;
use Carbon\Carbon;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    public function all_country() {
        $request_url = "https://corona.lmao.ninja/v2/countries/?yesterday=false&strict=true&allowNull=false";
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $request_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Cookie: __cfduid=d31e61411f94985a6c9d007936b9e8c301608803270"
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        foreach(json_decode($response) as $data) {
            // check island exists
            if(
                empty(Islands::where("name", $data->continent)->first()) &&
                !in_array($data->continent, ["", null])
            )
                Islands::insert([
                    "name" => $data->continent
                ]);
            // check country exists
            if(
                empty(Countrys::where("name", $data->country)->first()) &&
                !in_array($data->country, ["", null]) &&
                !in_array($data->continent, ["", null])
            )
                Countrys::insert([
                    "iso2" => $data->countryInfo->iso2,
                    "iso3" => $data->countryInfo->iso3,
                    "name" => $data->country,
                    "island_id" => (int)(Islands::where("name", $data->continent)->first()->id),
                    "lat_deg" => $data->countryInfo->lat,
                    "long_deg" => $data->countryInfo->long,
                    "flag_url" => $data->countryInfo->flag
                ]);
//            return ok(Carbon::createFromTimestamp($data->updated)->format("Y-m-d H:i:s"));
            $this_country = Countrys::where("name", $data->country)->first();
            Covid19_datas::insert([
                "country_id" => empty($this_country) ? null : (int)$this_country->id,
                "population" => $data->population,
                "api_updated_at" => (string)(Carbon::createFromTimestamp($data->updated)->format("Y-m-d H:i:s")),
                "oneCasePerPeople" => $data->oneCasePerPeople,
                "oneDeathPerPeople" => $data->oneDeathPerPeople,
                "deaths" => $data->deaths,
                "todayDeaths" => $data->todayDeaths,
                "source_json" => json_encode($data)
            ]);
        }
        return ok("update success");
    }
    public function write_daily(Request $request, $mmddYYYY) {
        $date =
            (int)substr($mmddYYYY, "0", "2") . "-" .
            (int)substr($mmddYYYY, "2", "2") . "-" .
            (int)substr($mmddYYYY, "4", "4");
        $request_url = "https://covid19.mathdro.id/api/daily/" . $date;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $request_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(

            )
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        foreach(json_decode($response) as $data) {
            // check country exists
            if(
                empty(Countrys::where("name", str_replace("*", "", $data->countryRegion))->first()) &&
                !in_array($data->countryRegion, ["", null]) &&
                !in_array($data->countryRegion, ["", null])
            )
                Countrys::insert([
                    "name" => str_replace("*", "", $data->countryRegion)
                ]);
            // check provinces exists
            $this_country = Countrys::where("name", str_replace("*", "", $data->countryRegion))->first();
            if(
                empty(
                    Provinces::where("name", str_replace("*", "", $data->provinceState))
                        ->where("country_id", $this_country->id)->first()
                ) &&
                !in_array($data->provinceState, ["", null]) &&
                !in_array($data->provinceState, ["", null])
            )
                Provinces::insert([
                    "name" => str_replace("*", "", $data->provinceState),
                    "country_id" => $this_country->id
                ]);
            $updated_at = (new Carbon((string)$data->lastUpdate))->format("Y-m-d H:i:s");
            Covid19_datas::insert([
                "country_id" => empty($this_country) ? null : (int)$this_country->id,
                "api_updated_at" => $updated_at,
                "deaths" => $data->deaths,
                "confirmed" => $data->confirmed,
                "source_json" => json_encode($data)
            ]);
        }
        return ok(json_decode($response));
    }
    public function write_full(Request $request, $begin, $end) {
        $mm = [
            1 => 31,
            2 => 29,
            3 => 31,
            4 => 30,
            5 => 31,
            6 => 30,
            7 => 31,
            8 => 31,
            9 => 30,
            10 => 31,
            11 => 30,
            12 => 31
        ];
        $begin = [
            "Y" => (int)substr($begin, 4, 4),
            "M" => (int)substr($begin, 0, 2),
            "D" => (int)substr($begin, 2, 2)
        ];
        $end = [
            "Y" => (int)substr($end, 4, 4),
            "M" => (int)substr($end, 0, 2),
            "D" => (int)substr($end, 2, 2)
        ];
        for($y = $begin["Y"]; $y <= $end["Y"]; $y++)
            for($m = $begin["M"]; $m <= $end["M"]; $m++)
                for($d = ($m == $begin["M"] ? $begin["D"] : 1); $d <= ($end["M"] == $m ? $end["D"] : $mm[$m]); $d++)
                    $this->write_daily($request, Carbon::createFromDate($y, $m, $d, 0)->format("mdY"));

        return ok("success write data from " .
            $begin['Y'] . "-" . $begin['M'] . "-" . $begin['D'] . " to " .
            $end['Y'] . "-" . $end['M'] . "-" . $end["D"]);
    }
}
