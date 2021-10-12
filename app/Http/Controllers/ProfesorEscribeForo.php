<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;
use App\Models\datosForo;
use App\Models\Foro;
use App\Models\archivosForo;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\ProfesorForoGrupo;

class ProfesorEscribeForo extends Controller
{
    public function index(Request $request)
    {
        return response()->json(ProfesorForoGrupo::where('idMateria', $request->idMateria)->where('idGrupo', $request->idGrupo)->first());
    }

/* 
    public function show(Request $request)
    {
        $mostrarMensajes=datosForo::all()->where('idForo', $request->idForo);
        return response()->json($mostrarMensajes);
    } */

    public function store(Request $request)
    
    {

        try {
            $nombre="";
                if($request->hasFile("archivo")){
                    $file=$request->archivo;
                   
                    /* if($file->guessExtension()=="pdf" || $file->guessExtension()=="jpg" ){ */
                        $nombreArchivo = time()."_".$request->nombre;      
                        Storage::disk('ftp')->put($nombreArchivo, fopen($request->archivo, 'r+'));              
                    /* } */
                }
               
                return response()->json(['status' => 'Success'], 200);            
                
             }catch (\Throwable $th) {
                    return response()->json(['status' => 'Error'], 406);
                     }
  }


        
    public function show(Request $request){
            if ($request->ou == 'Profesor'){
                $p=DB::table('profesor_estan_grupo_foro')
                ->select('datosForo.id AS id','datosForo.idForo AS idForo', 'datosForo.mensaje AS mensaje', 'datosForo.titulo AS titulo', 'datosForo.datos AS archivo')
                ->join('datosForo', 'datosForo.idForo', '=', 'profesor_estan_grupo_foro.idForo')
                ->where('profesor_estan_grupo_foro.idProfesor', $request->idUsuario)
                ->get();
               return response()->json($p);
             
            }else if($request->ou == 'Alumno'){
                $idGrupo = DB::table(' alumnos_pertenecen_grupos')->select('idGrupo')->where('idAlumno',$request->idUsuario)->first();
                $p=DB::table('profesor_estan_grupo_foro')
                ->select('datosForo.id AS id','datosForo.idForo AS idForo', 'datosForo.mensaje AS mensaje', 'datosForo.titulo AS titulo', 'datosForo.datos AS archivo')
                ->join('datosForo', 'datosForo.idForo', '=', 'profesor_estan_grupo_foro.idForo')
                ->where('profesor_estan_grupo_foro.idGrupo', $idGrupo)
                ->get();
               return response()->json($p);
                
               
            }

          
    }
        



    public function subirBD(Request $request){
        $datosForo = new datosForo;
        $datosForo->idForo = $request->idForo;
        $datosForo->idUsuario = $request->idUsuario;
        $datosForo->titulo = $request->titulo;
        $datosForo->mensaje = $request->mensaje;
        $datosForo->save();

        $idDatos = DB::table('datosForo')->orderBy('created_at', 'desc')->limit(1)->get('id');
        /* $nombreArchivosArray= []; */
        $nombreArchivosArray = explode(',', $request->nombre_archivos);
        foreach ($nombreArchivosArray as $nombres){   

        $archivosForo = new archivosForo;
        $archivosForo->idDato = $idDatos[0]->id;
        $archivosForo->idForo = $request->idForo;
        $archivosForo->nombreArchivo = time()."_".$nombres;
        $archivosForo->save();
    }
        return response()->json(['status' => 'Success'], 200);
    }

    public function traerArchivo(Request $request)
    {
        return Storage::disk('ftp')->get($request->archivo);
    }


    public function update(Request $request)
    {
        $modificarDatosForo = datosForo::where('id', $request->idDatos)->first();
       
        try{
            $modificarDatosForo->titulo = $request->titulo;
            $modificarDatosForo->mensaje = $request->mensaje;
            $modificarDatosForo->save();
            return response()->json(['status' => 'Success'], 200);
        } catch (\Throwable $th) {
            return response()->json(['status' => 'Bad Request'], 400);
        }
    }

    public function destroy(Request $request)
    {
        $eliminarDatosForo = datosForo::where('id', $request->idDatos)->first();
        try {
            $eliminarDatosForo->delete();
            return response()->json(['status' => 'Success'], 200);
        } catch (\Throwable $th) {
            return response()->json(['status' => 'Bad Request'], 400);
        }
    }
}
