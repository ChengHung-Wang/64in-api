<?php

namespace App\Http\Controllers;

use App\Models\Countrys;
use App\Models\Covid19_datas;
use App\Models\Islands;
use App\Models\Provinces;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
//    public function all_country() {
//        $request_url = "https://corona.lmao.ninja/v2/countries/?yesterday=false&strict=true&allowNull=false";
//        $curl = curl_init();
//        curl_setopt_array($curl, array(
//            CURLOPT_URL => $request_url,
//            CURLOPT_RETURNTRANSFER => true,
//            CURLOPT_ENCODING => "",
//            CURLOPT_MAXREDIRS => 10,
//            CURLOPT_TIMEOUT => 0,
//            CURLOPT_FOLLOWLOCATION => true,
//            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//            CURLOPT_CUSTOMREQUEST => "GET",
//            CURLOPT_HTTPHEADER => array(
//                "Cookie: __cfduid=d31e61411f94985a6c9d007936b9e8c301608803270"
//            ),
//        ));
//        $response = curl_exec($curl);
//        curl_close($curl);
//
//        foreach(json_decode($response) as $data) {
//            // check island exists
//            if(
//                empty(Islands::where("name", $data->continent)->first()) &&
//                !in_array($data->continent, ["", null])
//            )
//                Islands::insert([
//                    "name" => $data->continent
//                ]);
//            // check country exists
//            if(
//                empty(Countrys::where("name", $data->country)->first()) &&
//                !in_array($data->country, ["", null]) &&
//                !in_array($data->continent, ["", null])
//            )
//                Countrys::insert([
//                    "iso2" => $data->countryInfo->iso2,
//                    "iso3" => $data->countryInfo->iso3,
//                    "name" => $data->country,
//                    "island_id" => (int)(Islands::where("name", $data->continent)->first()->id),
//                    "lat_deg" => $data->countryInfo->lat,
//                    "long_deg" => $data->countryInfo->long,
//                    "flag_url" => $data->countryInfo->flag
//                ]);
////            return ok(Carbon::createFromTimestamp($data->updated)->format("Y-m-d H:i:s"));
//            $this_country = Countrys::where("name", $data->country)->first();
//            Covid19_datas::insert([
//                "country_id" => empty($this_country) ? null : (int)$this_country->id,
//                "population" => $data->population,
//                "api_updated_at" => (string)(Carbon::createFromTimestamp($data->updated)->format("Y-m-d H:i:s")),
//                "oneCasePerPeople" => $data->oneCasePerPeople,
//                "oneDeathPerPeople" => $data->oneDeathPerPeople,
//                "deaths" => $data->deaths,
//                "todayDeaths" => $data->todayDeaths,
//                "source_json" => json_encode($data)
//            ]);
//        }
//        return ok("update success");
//    }
    public function write_daily(Request $request, $date) {
        $date = join("-", array_map(function($text) {
            return (int)$text;
        }, explode("-", $date)));
        foreach($this->request_json("https://covid19.mathdro.id/api/daily/". $date) as $data) {
            if(! is_numeric($data->deaths) || ! is_numeric($data->recovered))
                continue;
            $location_info = $this->country_check($data->countryRegion, $data->provinceState);
            Covid19_datas::insert([
                "country_id" => $location_info['country_id'],
                "province_id" => $location_info['province_id'],
                "api_updated_at" => (new Carbon($data->lastUpdate))->format("Y-m-d H:i:s"),
                "deaths" => $data->deaths,
                "confirmed" => $data->confirmed,
                "recovered" => $data->recovered,
                "source_json" => json_encode($data)
            ]);
        }
        return ok();
    }


    public function write_full(Request $request, $begin, $end) {
        $begin = Carbon::createFromFormat("m-d-Y", $begin)->format("Y-m-d");
        $end = Carbon::createFromFormat('m-d-Y', $end)->format("Y-m-d");
        foreach(CarbonPeriod::create($begin, $end) as $date)
            $this->write_daily($request, $date->format("m-d-Y"));
        return ok("success insert $begin to $end data");
    }


    public function countries() {
        foreach(Countrys::all() as $main) {
            if($main->source_json != null)
                continue;
            $special_countries = [
                "Mainland China" => "china",
                "South Korea" =>"Korea (Democratic People's Republic of)",
                "North Macedonia" => "Macedonia (the former Yugoslav Republic of)",
                "North Ireland" => "United Kingdom of Great Britain and Northern Ireland",
                "Bosnia and Herzegovina" => "Bosnia and Herzegovina",
                "Vatican City" => "Holy See",
                "St. Martin" => "Saint Martin (French part)",
                "Hong Kong SAR" => "Hong Kong",
                "Taipei and environs" => "taiwan",
                "occupied Palestinian territory" => "Palestine, State of",
                "Macao SAR" => "Macao",
                "Channel Islands" => "Jersey",
                "Korea, South" => "Korea (Democratic People's Republic of)",
                "Cruise Ship" => "",
                "Czechia" => "Czech Republic",
                "Taiwan*" => "Taiwan",
                "Congo (Kinshasa)" => "Congo (Democratic Republic of the)",
                "Congo (Brazzaville)" => "Congo",
                "Republic of the Congo" => "Congo",
                "Gambia, The" => "Gambia",
                "Bahamas, The" => "Bahamas",
                "Cape Verde" => "Cabo Verde",
                "Diamond Princess" => "",
                "West Bank and Gaza" => "Palestine, State of"
            ];
            $search_name = $main->name;
            if(isset($special_countries[$main->name]))
                $search_name = $special_countries[$main->name];
            $data = $this->request_json("https://restcountries.eu/rest/v2/name/" . $search_name);
            if(! is_array($data))
                continue;
            $data = $data[0];

            $save_data = [
                "nativeName" => $data->nativeName,
                "iso2" => $data->alpha2Code,
                "iso3" => $data->alpha3Code,
                "lat_deg" => $data->latlng[0],
                "long_deg" => $data->latlng[1],
                "population" => $data->population,
                "flag_url" => $this->flag_fetch($data->flag),
                "source_json" => json_encode($data)
            ];
            if (empty(Islands::where("sub_name", $data->subregion)->first()))
                $save_data['island_id'] = (int)Islands::insertGetId(["name" => $data->region, "sub_name" => $data->subregion]);
            else
                $save_data['island_id'] = (int)Islands::where("sub_name", $data->subregion)->first()->id;
            $save_data['updated_at'] = Carbon::now();
            $main->update($save_data);
        }
        return ok();
    }

    public function flag_fetch($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        $file = curl_exec($ch);
        curl_close($ch);
        $file_path = "flag/" . md5($file) . "." . pathinfo($url, PATHINFO_EXTENSION);//儲存資料夾
        $resource = fopen(public_path() . "/" . $file_path, 'a');//新增檔案
        fwrite($resource, $file);//寫入媒體
        fclose($resource);//關閉檔案
        return $file_path;
    }


    public function request_json($url, $method = "GET") {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response);
    }

    /*
     * --------------------------------------------
     * this function only check basic data
     * --------------------------------------------
    */
    public function country_check($country, $province) {
        $return_data = [
            "country_id" => null,
            "province_id" => null
        ];

        if($country == "")
            return false;

        // process country
        if (empty(Countrys::where("name", $country)->first()))
            $return_data['country_id'] = (int)Countrys::insertGetId([
                "name" => $country
            ]);
        else
            $return_data["country_id"] = (int)Countrys::where("name", $country)->first()->id;

        // process province
        if ($province != "") {
            if (empty(Provinces::where([
                "country_id" => $return_data['country_id'],
                "name" => $province
            ])->first()))
                $return_data['province_id'] = (int)Provinces::insertGetId([
                    "name" => $province,
                    "country_id" => $return_data['country_id']
                ]);
            else
                $return_data["province_id"] = (int)Provinces::where([
                    "country_id" => $return_data['country_id'],
                    "name" => $province
                ])->first()->id;
        }
        return $return_data;
    }


}
