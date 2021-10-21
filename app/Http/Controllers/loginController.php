<?php
namespace App\Http\Controllers;
use App\Models\token;
use App\Models\usuarios;
use Illuminate\Http\Request;
use LdapRecord\Models\ActiveDirectory\User;
use Illuminate\Support\Str;
use LdapRecord\Connection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class loginController extends Controller
{

    public function index()
    {
        $allUsers =  User::all();
        return response()->json($allUsers);
    }

    public function connect(Request $request)
    {

        $connection = new Connection([
            'hosts' => ['192.168.1.73'],
        ]);
        $datos = self::traerDatos($request);
        $connection->connect();
        if ($connection->auth()->attempt($request->username . '@syntech.intra', $request->password, $stayBound = true)) {
            return [
                'connection' => 'Success',
                'datos' => $datos,
            ];
        } else {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }
    }
    public function traerDatos($request)
    {
        $u = usuarios::where('username', $request->username)->first();
        $datos = [
            "username" => $u->username,
            "nombre" => $u->nombre,
            "ou" => $u->ou,
            "imagen_perfil" => $u->imagen_perfil
        ];
        $base64data = base64_encode(json_encode($datos));
        $tExist = token::where('token', $base64data)->first();
        if ($tExist) {
            $tExist->delete();
            self::guardarToken($base64data);
        } else {
            self::guardarToken($base64data);
        }
        return  $base64data;
    }
    public function guardarToken($token)
    {
        $t = new token;
        $t->token = $token;
        $t->fecha_vencimiento = Carbon::now()->addMinutes(120);
        $t->save();
    }


    public function cargarImagen(Request $request)
    {
       try { 
            $nombre="";
                if($request->hasFile("archivo")){
                    $file=$request->archivo;
                    
                        $nombre = time()."_".$file->getClientOriginalName();                       
                        Storage::disk('ftp')->put($nombre, fopen($request->archivo, 'r+'));                  
                       

                }
                self::subirImagen($request, $nombre);
                return response()->json(['status' => 'Success'], 200);         
             }catch (\Throwable $th) {
                    return response()->json(['status' => 'Error'], 406);
            } 
    }

    public function subirImagen($request, $nombre)
    {
        try { 
            $usuarios = usuarios::where('username', $request->username)->first();
            if($usuarios){
                DB::update('UPDATE usuarios SET imagen_perfil="' . $nombre . '" WHERE username="' . $request->username . '";');  
            }
            return response()->json(['status' => 'Success'], 200);
        } catch (\Throwable $th) {
            return response()->json(['status' => 'Bad Request'], 400);
        
    }
            
                

                
    }

    public function traerImagen(Request $request)
    {
        $base64imagen = base64_encode(Storage::disk('ftp')->get($request->imagen_perfil));
        return $base64imagen;
       
    }


}
