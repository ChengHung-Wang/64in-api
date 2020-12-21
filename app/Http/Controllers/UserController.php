<?php

namespace App\Http\Controllers;

use App\Models\Avatars;
use App\Models\Users;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function login(Request $request) {
        $check = all_has([
            ['account',"str"],
            ['password',"str"]
        ],$request);
        if(! $check['has'])
            return err_MF();
        if(! $check['type'])
            return err_WDT();
        $this_user = Users::where("account",$request->get("account"))->first();
        if (empty($this_user) || ! password_verify($request->get("password"),$this_user->password))
            return err("MSG_INVALID_LOGIN", "403");
        $this_user->token = hash("sha256", $this_user->email);
        $this_user->save();
        return ok($this_user->token);
    }
    public function logout(Request $request) {
        $this_user = $request->get("this_user");
        $this_user->token = null;
        $this_user->save();
        return ok();
    }
    public function add(Request $request) {
        $check = all_has([
            ["email", "str"],
            ["password", "str"],
            ["account", "str"],
            ["female", "int"],
            ["name", "str"]
        ], $request);
        if(! $check['has'])
            return err_MF();
        if(! $check['type'])
            return err_WDT();
        if(!empty(
            Users::where("account", $request->get("account"))->first()
        ))
            return err("MSG_DUPLICATED_ACCOUNT_NAME", 409);
        $user_id = Users::insertGetId([
            "account" => $request->get("account"),
            "password" => password_hash($request->get("password"), PASSWORD_DEFAULT),
            "email" => $request->get("email"),
            "female" => $request->get("female"),
            "name" => $request->get('name')
        ]);
        return ok($user_id);
    }

    public function profile_edit(Request $request) {
        $this_user = $request->get("this_user");
        $check_rule = [
            ["email", "str"],
            ["password", "str"],
            ["account", "str"],
            ["female", "int"],
            ["name", "str"],
            ["old_password", "str"]
        ];
        if($request->hasFile("avatar"))
            array_push($check_rule, ["avatar", "photo"]);
        $check = all_has($check_rule, $request);
        if(! $check['has'])
            return err_MF();
        if(! $check['type'])
            return err_WDT();
        if(! $check["media_type"])
            return err("MSG_PHOTO_TYPE_IS_NOT_ALLOW", 409);
        if(!empty(Users::where("account", $request->get("account"))->where("id", "!=", $this_user->id)->first()))
            return err("MSG_DUPLICATED_ACCOUNT_NAME", 409);
        if (empty($this_user) || ! password_verify($request->get("old_password"),$this_user->password))
            return err("MSG_INVALID_OLD_PASSWORD", "403");
        $request->get("this_user")->update([
            "account" => $request->get("account"),
            "password" => password_hash($request->get("password"), PASSWORD_DEFAULT),
            "email" => $request->get("email"),
            "female" => $request->get("female"),
            "name" => $request->get('name')
        ]);
        if($request->hasFile('avatar')) {
            Avatars::insert([
                "user_id" => $this_user->id,
                "file_path" => $request->get("file_name")
            ]);
        }
        return ok(url($request->get("file_name")));
    }

    public function get_profile(Request $request) {
        $data = $request->get("this_user");
        if (!empty(Avatars::where("user_id", $request->get("this_user")->id)->first()))
            $data->avatar = url(Avatars::where("user_id", $request->get("this_user")->id)->orderBy("id", "desc")->first()->file_path);
        return ok($data);
    }
}
