<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeed extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            'name'=> 'SuperUser',
            'email'=> '00000000',
            'password'=> Hash::make('SuperUser0'),
            'created_at' =>Carbon::now(),
            'updated_at' =>Carbon::now(),
        ]);
        DB::table('usuarios')->insert([
            'user_id'=>1,
            'identificacion_usuario'=> '00000000',
            'nombre'=> 'SuperUser',
            'correo_electronico'=> 'correo@correo.com',
            'usuario_creacion_id'=> 1,
            'usuario_creacion_nombre'=> 'SuperUser',
            'usuario_modificacion_id'=>1,
            'usuario_modificacion_nombre'=> 'SuperUser',
            'created_at' =>Carbon::now(),
            'updated_at' =>Carbon::now(),
        ]);
        DB::table('roles')->insert([
            'name'=> 'SuperSu',
            'guard_name'=> 'api',
            'type'=> 'IN',
            'status'=>true,
            'creation_user_id' =>1,
            'creation_user_name'=>'SuperUser',
            'modification_user_id' =>1,
            'modification_user_name' =>'SuperUser',
            'created_at' =>Carbon::now(),
            'updated_at' =>Carbon::now(),
        ]);
        DB::table('model_has_roles')->insert([
            'role_id'=> 1,
            'model_type'=> 'App\Models\User',
            'model_id'=> 1,
        ]);
    }
}
