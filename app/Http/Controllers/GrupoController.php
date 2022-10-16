<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\listaClaseVirtual;
use App\Models\agendaClaseVirtual;
use App\Models\usuarios;
use App\Http\Controllers\RegistrosController;
use Carbon\Carbon;
use App\PDF;

class GrupoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function listarAlumnos(Request $request)
    {
        $alumnos = $this->getAlumnosGrupoMateria($request);
        $profesor = $this->getProfesorGrupoMateria($request);

        $p = [
            "idGrupo" => $profesor[0]->idGrupo,
            "idProfesor" => $profesor[0]->idProfesor,
            "nombre" => $profesor[0]->nombreProfesor,
            "imagen_perfil" => base64_encode(Storage::disk('ftp')->get($profesor[0]->imagen_perfil)),
        ];
        $listaAlumnos = array();

        foreach ($alumnos as $a) {

            $alumno = [
                "idGrupo" => $a->idGrupo,
                "idAlumnos" => $a->idAlumnos,
                "nombre" => $a->nombreAlumno,
                "imagen_perfil" => base64_encode(Storage::disk('ftp')->get($a->imagen_perfil)),
            ];
            array_push($listaAlumnos, $alumno);
        }


        $data = [
            "Profesor" => $p,
            "Alumnos" => $listaAlumnos,
        ];
        return response()->json($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {

            foreach ($request->presentes as $presente) {
                $this->insertPresentesAulaVirtual($request, $presente);
            }
            foreach ($request->ausentes as $ausente) {
                $this->insertAusentesAulaVirtual($request, $ausente);
            }
            RegistrosController::store("LISTA",$request->header('token'),"CREATE","");

         return response()->json(['status' => 'Success'], 200);
       } catch (\Throwable $th) {
           return response()->json(['status' => 'Bad Request'], 400);
        }
    }


    public function index(Request $request)
    {
        return  self::registroListarTodo($request->idProfesor);
    }

    public function registroListarTodo($idProfesor)
    {
        return response()->json(DB::table('lista_aula_virtual')
            ->select('lista_aula_virtual.idClase', 'agenda_clase_virtual.idGrupo', 'agenda_clase_virtual.idProfesor as IdProfesor', 'materias.nombre as materia', 'materias.id AS idMateria', 'lista_aula_virtual.created_at')
            ->join('agenda_clase_virtual', 'lista_aula_virtual.idClase', '=', 'agenda_clase_virtual.id')
            ->join('materias', 'agenda_clase_virtual.idMateria', '=', 'materias.id')
            ->where('agenda_clase_virtual.idProfesor', $idProfesor)
            ->distinct()
            ->get());
    }



    public function mostrarFaltasTotalesGlobal(Request $request)
    {

        $alumnos = $this->getAlumnosGrupoMateria($request);
        $cantClasesListadas = $this->getCantidadClases($request);

        $listadoAlumnos = array();

    foreach ($alumnos as $a) {

        $cantFaltas = $this->getCantidadFaltas($request, $a);

        $fechas_ausencia = $this->getFechasFaltas($request, $a);
        $alumno = [
            "idAlumno" => $a->idAlumnos,
            "nombreAlumno" => $a->nombreAlumno,
            "fechas_ausencia"=> $fechas_ausencia,
            "cantidad_faltas" => $cantFaltas[0]->totalClase,
            "total_clases" => $cantClasesListadas[0]->totalClase
        ];
        array_push($listadoAlumnos, $alumno);
    }
    return response()->json($listadoAlumnos);

    }

    public function mostrarFaltasTotalesGlobalPorMes(Request $request)
    {

        $fecha_1 = Carbon::parse($request->fecha_filtro);
        $peticionSQL = DB::table('lista_aula_virtual')
            ->select('lista_aula_virtual.idAlumnos', DB::raw('count(*) as total'))
            ->join('agenda_clase_virtual', 'lista_aula_virtual.idClase', '=', 'agenda_clase_virtual.id')
            ->where('agenda_clase_virtual.idMateria', $request->idMateria)
            ->where('agenda_clase_virtual.idGrupo', $request->idGrupo)
            ->where('lista_aula_virtual.asistencia', "0")
            ->whereYear('created_at', $fecha_1('Y'))        //->whereYear('created_at', date('Y')) EJEMPLO->whereYear('created_at', date('Y'))
            ->groupBy('idAlumnos')
            ->get();
        /*
        $alumno=$peticionSQL[0]->idAlumnos;
        $faltas=0;

        $dataResponse = array();
        foreach ($peticionSQL as $p) {
            $fecha_1 = Carbon::parse($request->fecha_filtro)->format('m');
            $fecha_2 = Carbon::parse($p->created_at)->format('m');

            if($fecha_1 === $fecha_2){  ///COMPARA FECHAS PARA VER SI ES EL MIMSMO MES DE FILTRO
                $alumno2=$p->idAlumnos;

            if($alumno == $alumno2){ ///COMPARA SI EL ALUMNO SIGUE SIENDO EL MISMO PARA PODER SUMARLE LA FATLA
                $faltas=$faltas+1;
                $alumno=$p->idAlumnos;
            }else{ ///CUANDO EL ALUMNO ES DIFERENTE, TOMA EL ANTERIOR ALUMNO Y GUARADA LAS FALTAS, Y PONE FALTAS=1 PARA EL NUEVO y ALUMNO LO IGUALA AL NUEVO
                $datos = [
                    "idAlumnos" => $alumno,
                    "faltas" => $faltas,
                ];

                array_push($dataResponse, $datos);
                $fatlas=1;
                $alumno=$p->idAlumnos;
            }

            }
                    } */

        return response()->json($peticionSQL);
    }



    public function registroClase(Request $request)
    {

        $registroClase = listaClaseVirtual::all()->where('idClase', $request->idClase);
        $chequeo = "";
        $dataResponse = array();
        foreach ($registroClase as $p) {
            $usuarios = usuarios::where('id', $p->idAlumnos)->first();
            if ($p->asistencia == "1") {
                $chequeo = "Presente";
            } else {
                $chequeo = "Ausente";
            }
            $datos = [
                "idClase" => $p->idClase,
                "idAlumno" => $p->idAlumnos,
                "asistencia" => $chequeo,
                "nombre" => $usuarios->nombre,
                "imagen_perfil" => base64_encode(Storage::disk('ftp')->get($usuarios->imagen_perfil)),
            ];

            array_push($dataResponse, $datos);
        }

        return response()->json($dataResponse);
    }

    public function registroAlumno(Request $request)
    {

        $registroAlumno = listaClaseVirtual::all()->where('idALumnos', $request->idAlumnos);


        foreach ($registroAlumno as $p) {
            $usuarios = usuarios::where('id', $p->idAlumnos)->first();
            if ($p->asistencia == "1") {
                $chequeo = true;
            } else {
                $chequeo = false;
            }
            $datos = [
                "idClase" => $p->idClase,
                "idAlumno" => $p->idAlumnos,
                "asistencia" => $chequeo,
                "nombre" => $usuarios->nombre,
                "imagen_perfil" => base64_encode(Storage::disk('ftp')->get($usuarios->imagen_perfil)),
            ];

            array_push($dataResponse, $datos);
        }

        return response()->json($dataResponse);
    }






    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        try {

            foreach ($request->presentes as $presente) {
                DB::update('UPDATE lista_aula_virtual set asistencia = 1 where idAlumnos = ?  AND idClase= ?', [$presente,  $request->idClase]);
            }
            foreach ($request->ausentes as $ausente) {
                DB::update('UPDATE lista_aula_virtual set asistencia = 0 where idAlumnos = ? AND idClase= ?', [$ausente, $request->idClase]);
            }

            RegistrosController::store("LISTA",$request->header('token'),"UPDATE","");

            return response()->json(['status' => 'Success'], 200);
        } catch (\Throwable $th) {
            return response()->json(['status' => 'Bad Request'], 400);
        }
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * @param Request $request
     * @return \Illuminate\Support\Collection
     */
    public function getAlumnosGrupoMateria(Request $request): \Illuminate\Support\Collection
    {
        $alumnos = DB::table('alumnos_pertenecen_grupos')
            ->select('alumnos_pertenecen_grupos.idGrupo AS idGrupo', 'alumnos_pertenecen_grupos.idAlumnos as idAlumnos', 'usuarios.nombre as nombreAlumno', 'usuarios.imagen_perfil')
            ->join('usuarios', 'usuarios.id', '=', 'alumnos_pertenecen_grupos.idAlumnos')
            ->join('profesor_estan_grupo_foro', 'profesor_estan_grupo_foro.idGrupo', '=', 'alumnos_pertenecen_grupos.idGrupo')
            ->where('alumnos_pertenecen_grupos.idGrupo', $request->idGrupo)
            ->where('profesor_estan_grupo_foro.idMateria', $request->idMateria)
            ->get();
        return $alumnos;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Support\Collection
     */
    public function getProfesorGrupoMateria(Request $request): \Illuminate\Support\Collection
    {
        $profesor = DB::table('profesor_estan_grupo_foro')
            ->select('profesor_estan_grupo_foro.idGrupo AS idGrupo', 'profesor_estan_grupo_foro.idProfesor', 'usuarios.nombre as nombreProfesor', 'usuarios.imagen_perfil')
            ->join('usuarios', 'usuarios.id', '=', 'profesor_estan_grupo_foro.idProfesor')
            ->where('profesor_estan_grupo_foro.idGrupo', $request->idGrupo)
            ->where('profesor_estan_grupo_foro.idMateria', $request->idMateria)
            ->get();
        return $profesor;
    }

    /**
     * @param Request $request
     * @param $presente
     * @return void
     */
    public function insertPresentesAulaVirtual(Request $request, $presente): void
    {
        DB::insert('INSERT into lista_aula_virtual (idClase, idAlumnos, asistencia, created_at , updated_at) VALUES (?, ?, ?, ? , ?)', [$request->idClase, $presente, 1, Carbon::now(), Carbon::now()]);
    }

    /**
     * @param Request $request
     * @param $ausente
     * @return void
     */
    public function insertAusentesAulaVirtual(Request $request, $ausente): void
    {
        DB::insert('INSERT into lista_aula_virtual (idClase, idAlumnos, asistencia, created_at , updated_at) VALUES (?, ?, ?, ? , ?)', [$request->idClase, $ausente, 0, Carbon::now(), Carbon::now()]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Support\Collection
     */
    public function getCantidadClases(Request $request): \Illuminate\Support\Collection
    {
        $cantClasesListadas = DB::table('agenda_clase_virtual')
            ->select(DB::raw('count(*) as totalClase'))
            ->join('lista_aula_virtual', 'agenda_clase_virtual.id', '=', 'lista_aula_virtual.idClase')
            ->where('agenda_clase_virtual.idMateria', $request->idMateria)
            ->where('agenda_clase_virtual.idGrupo', $request->idGrupo)
            ->get();
        return $cantClasesListadas;
    }

    /**
     * @param Request $request
     * @param $a
     * @return \Illuminate\Support\Collection
     */
    public function getCantidadFaltas(Request $request, $a): \Illuminate\Support\Collection
    {
        $cantFaltas = DB::table('agenda_clase_virtual')
            ->select(DB::raw('count(*) as totalClase'))
            ->join('lista_aula_virtual', 'agenda_clase_virtual.id', '=', 'lista_aula_virtual.idClase')
            ->where('agenda_clase_virtual.idMateria', $request->idMateria)
            ->where('agenda_clase_virtual.idGrupo', $request->idGrupo)
            ->where('lista_aula_virtual.idAlumnos', $a->idAlumnos)
            ->where('lista_aula_virtual.asistencia', '0')
            ->get();
        return $cantFaltas;
    }

    /**
     * @param Request $request
     * @param $a
     * @return \Illuminate\Support\Collection
     */
    public function getFechasFaltas(Request $request, $a): \Illuminate\Support\Collection
    {
        $fechas_ausencia = DB::table('agenda_clase_virtual')
            ->select('agenda_clase_virtual.fecha_inicio as fecha_clase')
            ->join('lista_aula_virtual', 'agenda_clase_virtual.id', '=', 'lista_aula_virtual.idClase')
            ->where('agenda_clase_virtual.idMateria', $request->idMateria)
            ->where('agenda_clase_virtual.idGrupo', $request->idGrupo)
            ->where('lista_aula_virtual.idAlumnos', $a->idAlumnos)
            ->where('lista_aula_virtual.asistencia', '0')
            ->get();
        return $fechas_ausencia;
    }
}
