<?php

namespace App\Http\Controllers;

use App\Models\Countrys;
use App\Models\Islands;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class Covid19Controller extends Controller
{
    public function get_islands() {
        return ok(Islands::get()->groupBy("name"));
    }

    public function get_countries(Request $request) {
        $island_id = $request->route("island_id");
        $data = [];
        if($request->has("full") && $request->get("full") == "true")
            $search_data = Countrys::whereIn("island_id", Islands::where("name", $island_id)->pluck("id"))->get();
        else
            $search_data = Countrys::where("island_id", $island_id)->get();
        foreach($search_data as $main) {
            $main->flag_url = url($main->flag_url);
            $main->target_ids = Countrys::where("name", $main->name)->pluck("id");
            $main->island = Islands::find($main->island_id);
            if(! in_array($main->name, array_map(function($e) {
                return $e->name;
            }, $data)))
                array_push($data, Arr::except($main, [
                    "island_id",
                    "source_json",
                    "created_at",
                    "updated_at"
                ]));
        }
        return ok($data);
    }
}
