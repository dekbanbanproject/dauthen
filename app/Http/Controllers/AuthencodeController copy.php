<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Authencode;
use App\Models\Vn_insert;
use App\Models\Pttypehistory;
use App\Models\Ovst;
use App\Models\Ptdepart;
use App\Models\Service_time;
use App\Models\Opitemrece;
use App\Models\Visit_pttype;
use App\Models\Ovst_finance;
use App\Models\Opd_regist_sendlist;
use App\Models\Opdscreen;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\support\Facades\Hash;
use Illuminate\support\Facades\Validator;
// use Illuminate\support\Facades\Http;
use Stevebauman\Location\Facades\Location;
use Http;
use SoapClient;
use File;
use SplFileObject;
use Arr;
use Storage;
use GuzzleHttp\Client;

class AuthencodeController extends Controller
{
    public function authen_main(Request $request)
    {
        $ip = $request->ip();
        $terminals = Http::get('http://localhost:8189/api/smartcard/terminals')->collect();
        $cardcid = Http::get('http://localhost:8189/api/smartcard/read')->collect();
        $cardcidonly = Http::get('http://localhost:8189/api/smartcard/read-card-only')->collect();
        $output = Arr::sort($terminals);
        $outputcard = Arr::sort($cardcid);
        $outputcardonly = Arr::sort($cardcidonly);

        // $client->get('http://' . $ip . ':8189/api/smartcard/terminals', ['verify' => true]);

        // $client = new Client();
        // $client->get('/', ['verify' => true]);    
        // $response = $client->get('https://api.github.com/');    
        // dd($terminals);
        // $client->setDefaultOption(
        //     'verify', 
        //     'C:\Program Files (x86)\Git\bin\curl-ca-bundle.crt'
        // );
        // dd($terminals['status']);
        // if ($terminals['status'] == '500') {
        if ($output == []) {
            $smartcard = 'NO_CONNECT';
            $smartcardcon = '';
            // dd($smartcard);
            return view('authen_main', [
                'smartcard'          =>  $smartcard,
                'cardcid'            =>  $cardcid,
                'smartcardcon'       =>  $smartcardcon,
                'output'             =>  $output,

            ]);
        } else {
            $smartcard = 'CONNECT';
            // dd($smartcard);
            foreach ($output as $key => $value) {
                $terminalname = $value['terminalName'];
                $cardcids = $value['isPresent'];
            }

            // dd($cardcids);
            if ($cardcids != 'false') {
                $smartcardcon = 'NO_CID';

                return view('authen_main', [
                    'smartcard'          =>  $smartcard,
                    'cardcid'            =>  $cardcid,
                    'smartcardcon'       =>  $smartcardcon,
                    'output'             =>  $output,
                ]);
                // dd($smartcardcon);   
            } else {
                $smartcardcon = 'CID_OK';
                // dd($smartcardcon);  
                // $collection = Http::get('http://' . $ip . ':8189/api/smartcard/read?readImageFlag=true')->collect();
                $collection = Http::get('http://localhost:8189/api/smartcard/read?readImageFlag=true')->collect();
                // $patient =  DB::connection('mysql')->select('select cid,hometel from patient limit 10');
                $output2 = Arr::sort($collection);
                $hcode = $output2['hospMain']['hcode'];
                //  dd($hcode);   
                $pidscheck = $collection['pid'];            
                $token_data = DB::connection('mysql2')->select('SELECT * FROM nhso_token ORDER BY update_datetime desc limit 1');
                foreach ($token_data as $key => $value) {
                    $cid_    = $value->cid;
                    $token_  = $value->token;
                }
                $client = new SoapClient(
                    "http://ucws.nhso.go.th/ucwstokenp1/UCWSTokenP1?wsdl",
                    array("uri" => 'http://ucws.nhso.go.th/ucwstokenp1/UCWSTokenP1?xsd=1', "trace" => 1, "exceptions" => 0, "cache_wsdl" => 0)
                );
                $params = array(
                    'sequence' => array(
                        "user_person_id" => "$cid_",
                        "smctoken"       => "$token_",
                        "person_id"      => "$pidscheck"
                    )
                );
                $contents = $client->__soapCall('searchCurrentByPID', $params);
                // dd($contents);
                //   dd($hcode);
                foreach ($contents as $v) {
                    @$status                   = $v->status;
                    @$maininscl                = $v->maininscl;  // maininscl": "WEL"
                    @$startdate                = $v->startdate;  //"25650728"
                    @$hmain                    = $v->hmain;   //"11066"
                    @$subinscl                  = $v->subinscl;    //subinscl": "73"
                    @$person_id_nhso           = $v->person_id;
                    if (@$maininscl == 'WEL') {
                        @$cardid                    = $v->cardid;  // "R73450035286038"
                    } else {
                        $cardid = '';
                    } 
                    @$hmain_op                 = $v->hmain_op;  //"10978"
                    @$hmain_op_name            = $v->hmain_op_name;  //"รพ.ภูเขียวเฉลิมพระเกียรติ"
                    @$hsub                     = $v->hsub;    //"04047"
                    @$hsub_name                = $v->hsub_name;   //"รพ.สต.แดงสว่าง"
                    @$subinscl_name            = $v->subinscl_name; //"ช่วงอายุ 12-59 ปี"
                    @$primary_amphur_name      = $v->primary_amphur_name;  // อำเภอ  "โพนทอง"
                    @$primary_moo              = $v->primary_moo;    //หมู่ที่ 01
                    @$primary_mooban_name      = $v->primary_mooban_name;  // ชื่อหมู่บ้าน  "หนองนกแก้ว"
                    @$primary_tumbon_name      = $v->primary_tumbon_name;   //ชื่อตำบล   "สระนกแก้ว"
                    @$primary_province_name    = $v->primary_province_name;  //ชื่อจังหวัด
                }
                $check_cid = DB::connection('mysql2')->table('patient')->where('cid','=',$collection['pid'])->count();
                $check_hcode = DB::connection('mysql')->table('orginfo')->where('orginfo_id','=','1')->first();
                if ($check_cid > 0) {
                            $data_patient_ = DB::connection('mysql2')->select(' 
                                        SELECT p.hn ,pe.pttype_expire_date as expiredate ,pe.pttype_hospmain as hospmain ,pe.pttype_hospsub as hospsub 
                                        ,p.pttype,pt.name as ptname,pt.hipdata_pttype, pe.pttype_no as pttypeno ,pe.pttype_begin_date as begindate,p.cid,p.hcode,p.last_visit,p.hometel
                                        ,p.bloodgrp,p.addrpart,p.informname,p.informrelation,p.informtel,p.fathername,p.fatherlname
                                        ,p.father_cid,p.mathername,p.motherlname,p.mother_cid,p.spsname,p.spslname
                                        ,h.chwpart,h.amppart,h.tmbpart,h.po_code
                                        FROM patient p 
                                        LEFT OUTER JOIN person pe ON pe.patient_hn = p.hn 
                                        LEFT OUTER JOIN pttype pt ON pt.pttype = p.pttype
                                        LEFT OUTER JOIN hospcode h ON h.chwpart = p.chwpart AND h.amppart = p.amppart AND h.tmbpart = p.tmbpart
                                        WHERE p.cid = "'.$collection['pid'].'"
                                        GROUP BY p.hn
                            ');
                            foreach ($data_patient_ as $key => $value) {
                                $pids          = $value->cid;
                                $hcode         = $value->hcode;
                                $hn            = $value->hn;
                                $last_visit    = $value->last_visit;
                                $hometel       = $value->hometel;
                                $chwpart       = $value->chwpart;
                                $amppart       = $value->amppart;
                                $tmbpart       = $value->tmbpart;
                                $addrpart       = $value->addrpart;
                                $po_code       = $value->po_code;
                                $bloodgrp      = $value->bloodgrp;

                                $informname      = $value->informname;
                                $informrelation  = $value->informrelation;
                                $informtel       = $value->informtel;
                                $fathername      = $value->fathername;
                                $fatherlname     = $value->fatherlname;
                                $father_cid      = $value->father_cid;
                                $mathername      = $value->mathername;
                                $motherlname     = $value->motherlname;
                                $mother_cid      = $value->mother_cid;
                                $spsname         = $value->spsname;
                                $spslname        = $value->spslname;
                                $ptname          = $value->ptname;
                                $hipdata_pttype  = $value->hipdata_pttype;
                                $pttype_s        = $value->pttype;

                            }
                           
                            $check_pttype = DB::connection('mysql2')->table('pttype')->where('hipdata_pttype','=',$subinscl)->where('pttype','=',$pttype_s)->get();


                } else {
                        $pids            = $collection['pid'];
                        $hcode           = $check_hcode->orginfo_code;
                        $hn              = '';
                        $last_visit      = '';
                        $hometel         = '';
                        $chwpart         = $primary_province_name;
                        $amppart         = $primary_amphur_name;
                        $tmbpart         = $primary_tumbon_name;
                        $addrpart        = '';
                        $po_code         = '';
                        $bloodgrp        = '';
                        $informname      = '';
                        $informrelation  = '';
                        $informtel       = '';
                        $fathername      = '';
                        $fatherlname     = '';
                        $father_cid      = '';
                        $mathername      = '';
                        $motherlname     = '';
                        $mother_cid      = '';
                        $spsname         = '';
                        $spslname        = '';
                        $ptname          = '';
                        $hipdata_pttype  = '';
                        $pttype_s          = '';

                        // $check_pttype = DB::connection('mysql10')->table('pttype')->where('hipdata_pttype','=',$subinscl)->get();
                    
                }
                
                
                // dd($check_pttype);
                // $check_pttype = DB::connection('mysql10')->table('pttype')->where('hipdata_pttype','=',$subinscl)->where('pttype','=',$pttype)->get();
                // $data['check_pttype'] = DB::connection('mysql10')->table('pttype')->where('hipdata_pttype','=',$subinscl)->get();
                // dd($hcode);
                $year = substr(date("Y"), 2) + 43;
                $mounts = date('m');
                $day = date('d');
                $time = date("His");
                $vn = $year . '' . $mounts . '' . $day . '' . $time;
                $time_s = date("H:i:s");

                $date = date('Y-m-d');
                // dd($vn);OK
                $getvn_stat =  DB::connection('mysql2')->select('select * from vn_stat limit 2');
                $get_ovst =  DB::connection('mysql2')->select('select * from ovst limit 2');
                $get_opdscreen =  DB::connection('mysql2')->select('select * from opdscreen limit 2');
                $get_ovst_seq =  DB::connection('mysql2')->select('select * from ovst_seq limit 2');
                $get_spclty =  DB::connection('mysql2')->select('select * from spclty');
                $data['ovstist'] =  DB::connection('mysql2')->select('select * from ovstist');
                $data['spclty'] =  DB::connection('mysql2')->select('select * from spclty');
                $data['kskdepartment'] =  DB::connection('mysql2')->select('select * from kskdepartment');
                $data['pt_priority'] =  DB::connection('mysql2')->select('select * from pt_priority order by id');
                $data['pt_walk'] =  DB::connection('mysql2')->select('select * from pt_walk');
                $data['pt_subtype'] =  DB::connection('mysql2')->select('select * from pt_subtype order by pt_subtype');
                $data['pname'] =  DB::connection('mysql2')->select('select * from pname order by name');
                $data['marrystatus'] =  DB::connection('mysql2')->select('select code,name from marrystatus');
                $data['nationality'] =  DB::connection('mysql2')->select(' select nationality as code,name from nationality order by nationality desc');
                $data['thaiaddress_provine'] =  DB::connection('mysql2')->select('select chwpart,name from thaiaddress WHERE codetype="1"');
                $data['thaiaddress_amphur'] =  DB::connection('mysql2')->select('select amppart,name from thaiaddress WHERE codetype="2"');
                $data['thaiaddress_tumbon'] =  DB::connection('mysql2')->select('select tmbpart,name from thaiaddress WHERE codetype="3"');
                $data['thaiaddress_po_code'] =  DB::connection('mysql2')->select('SELECT chwpart,amppart,tmbpart,po_code FROM hospcode WHERE po_code <>"" GROUP BY po_code');
                $data['blood_group'] =  DB::connection('mysql2')->select('select name from blood_group order by name');
                $data['informrelation_list'] =  DB::connection('mysql2')->select('select name from informrelation_list');
                $data['religion'] =  DB::connection('mysql2')->select('SELECT * FROM religion');
                $data['occupation'] =  DB::connection('mysql2')->select('SELECT * FROM occupation');
                $data_patient =  DB::connection('mysql2')->select('SELECT pttype FROM patient WHERE cid = "'.$collection['pid'].'" ');
                $data['pttype'] =  DB::connection('mysql2')->select('SELECT * FROM pttype');

                foreach ($data_patient as $key => $value_pat) {
                    $check_pttype_ = DB::connection('mysql2')->table('pttype')->where('hipdata_pttype','=',$subinscl)->where('pttype','=',$value_pat->pttype)->first();
                }
                // $ori_pttype  = $check_pttype_->pttype;
                // dd($ori_pttype);
                // $data['thaiaddress_provinces'] =  DB::connection('mysql2')->select(' select * from thaiaddress_provinces');
                // $data['thaiaddress_amphures'] =  DB::connection('mysql2')->select(' select * from thaiaddress_amphures');
                // $data['thaiaddress_districts'] =  DB::connection('mysql2')->select(' select * from thaiaddress_districts');
                

                //ที่เก็บรูปภาพ
                $data['patient_image'] =  DB::connection('mysql2')->select('select * from patient_image where image_name = "OPD" limit 100');
                // dd($hn);
                if ($hn == '') {
                    $ovst_key = '';
                } else {
                     ///// เจน  ovst_key  จาก Hosxp
                    // $getovst_key_ = Http::get('https://cloud4.hosxp.net/api/ovst_key?Action=get_ovst_key&hospcode="' . $hcode . '"&vn="' . $vn . '"&computer_name=abcde&app_name=AppName&fbclid=IwAR2SvX7NJIiW_cX2JYaTkfAduFqZAi1gVV7ftiffWPsi4M97pVbgmRBjgY8')->collect();
                    // $output5 = Arr::sort($getovst_key_);
                    // $ovst_key = $output5['result']['ovst_key'];
                    $ovst_key = '';
                }
                 
                ///// เจน  hos_guid  จาก Hosxp
                $data_key = DB::connection('mysql2')->select('SELECT uuid() as keygen');
                $output4 = Arr::sort($data_key);
                foreach ($output4 as $key => $value_) {
                    $hos_guid = $value_->keygen;
                }

                
              
                // foreach ($output5 as $key => $value_ovst_key) { 
                //     $ovst_key = $value_ovst_key->ovst_key; 
                // }
                // dd($cardid);
                return view('authen_main', $data, [
                    'smartcard'                  =>  $smartcard,
                    'cardcid'                    =>  $cardcid,
                    'smartcardcon'               =>  $smartcardcon,
                    'hometel'                    =>  $hometel,
                    'vn'                         =>  $vn,
                    'hn'                         =>  $hn,
                    'chwpart'                    =>  $chwpart,
                    'amppart'                    =>  $amppart,
                    'tmbpart'                    =>  $tmbpart,
                    'po_code'                    =>  $po_code,
                    'bloodgrp'                   =>  $bloodgrp,
                    'addrpart'                   =>  $addrpart,
                    'last_visit'                 =>  $last_visit,
                    'hcode'                      =>  $hcode,
                    'hos_guid'                   =>  $hos_guid,
                    'ovst_key'                   =>  $ovst_key,
                    'time'                       =>  $time,
                    'collection1'                => $collection['pid'],
                    'collection2'                => $collection['fname'],
                    'collection3'                => $collection['lname'],
                    'collection4'                => $collection['birthDate'],
                    'collection5'                => $collection['transDate'],
                    'collection6'                => $collection['mainInscl'],
                    'collection7'                => $collection['subInscl'],
                    'collection8'                => $collection['age'],
                    'collection9'                => $collection['checkDate'],
                    'collection10'               => $collection['correlationId'],
                    'collection11'               => $collection['checkDate'],
                    'collection12'               => $collection['image'],
                    'collection13'               => $collection['sex'],
                    'collection14'               => $collection['nation'],
                    'collection15'               => $collection['titleName'],
                    'time_s'                     => $time_s,
                    'date'                       => $date,
                    'primary_moo'                => $primary_moo ,
                    'primary_tumbon_name'        => $primary_tumbon_name ,
                    'primary_amphur_name'        => $primary_amphur_name ,
                    'primary_province_name'      => $primary_province_name ,
                    // 'check_pttype'               => $check_pttype, 
                    'informname'                 =>$informname,
                    'informrelation'             =>$informrelation,
                    'informtel'                  =>$informtel ,
                    'fathername'                 =>$fathername ,
                    'fatherlname'                =>$fatherlname ,
                    'father_cid'                 =>$father_cid,
                    'mathername'                 =>$mathername,
                    'motherlname'                =>$motherlname,
                    'mother_cid'                 =>$mother_cid,
                    'spsname'                    =>$spsname,
                    'spslname'                   =>$spslname,
                    'ptname'                     =>$ptname,
                    'hipdata_pttype'             =>$hipdata_pttype,
                    'subinscl'                   =>$subinscl,
                    // 'ori_pttype'                 =>$ori_pttype,
                    'pttype_s'                   =>$pttype_s
                ]);
            }
        }
        
        // if ($output == []) {

        //     $smartcard = 'NO_CONNECT';
        //     $smartcardcon = '';
        //     // dd($smartcard);
        //     return view('authen.authen_main', [
        //         'smartcard'          =>  $smartcard,
        //         'cardcid'            =>  $cardcid,
        //         'smartcardcon'       =>  $smartcardcon,
        //         'output'             =>  $output,

        //     ]);
        //     // dd($smartcard);
        // } else {

        //     $smartcard = 'CONNECT';
        //     // dd($smartcard);
        //     foreach ($output as $key => $value) {
        //         $terminalname = $value['terminalName'];
        //         $cardcids = $value['isPresent'];
        //     }

        //     // dd($cardcids);
        //     if ($cardcids != 'false') {
        //         $smartcardcon = 'NO_CID';

        //         return view('authen.authen_main', [
        //             'smartcard'          =>  $smartcard,
        //             'cardcid'            =>  $cardcid,
        //             'smartcardcon'       =>  $smartcardcon,
        //             'output'             =>  $output,
        //         ]);
        //         // dd($smartcardcon);   
        //     } else {
        //         $smartcardcon = 'CID_OK';
        //         // dd($smartcardcon);  
        //         $collection = Http::get('http://' . $ip . ':8189/api/smartcard/read?readImageFlag=true')->collect();
        //         // $patient =  DB::connection('mysql')->select('select cid,hometel from patient limit 10');
        //         $output2 = Arr::sort($collection);
        //         $hcode = $output2['hospMain']['hcode'];
        //         //  dd($collection['pid']);   
        //         $pidscheck = $collection['pid'];            
        //         $token_data = DB::connection('mysql10')->select('SELECT * FROM nhso_token ORDER BY update_datetime desc limit 1');
        //         foreach ($token_data as $key => $value) {
        //             $cid_    = $value->cid;
        //             $token_  = $value->token;
        //         }
        //         $client = new SoapClient(
        //             "http://ucws.nhso.go.th/ucwstokenp1/UCWSTokenP1?wsdl",
        //             array("uri" => 'http://ucws.nhso.go.th/ucwstokenp1/UCWSTokenP1?xsd=1', "trace" => 1, "exceptions" => 0, "cache_wsdl" => 0)
        //         );
        //         $params = array(
        //             'sequence' => array(
        //                 "user_person_id" => "$cid_",
        //                 "smctoken"       => "$token_",
        //                 "person_id"      => "$pidscheck"
        //             )
        //         );
        //         $contents = $client->__soapCall('searchCurrentByPID', $params);
        //         // dd($contents);
        //         //   dd($hcode);
        //         foreach ($contents as $v) {
        //             @$status                   = $v->status;
        //             @$maininscl                = $v->maininscl;  // maininscl": "WEL"
        //             @$startdate                = $v->startdate;  //"25650728"
        //             @$hmain                    = $v->hmain;   //"11066"
        //             @$subinscl                  = $v->subinscl;    //subinscl": "73"
        //             @$person_id_nhso           = $v->person_id;
        //             if (@$maininscl == 'WEL') {
        //                 @$cardid                    = $v->cardid;  // "R73450035286038"
        //             } else {
        //                 $cardid = '';
        //             } 
        //             @$hmain_op                 = $v->hmain_op;  //"10978"
        //             @$hmain_op_name            = $v->hmain_op_name;  //"รพ.ภูเขียวเฉลิมพระเกียรติ"
        //             @$hsub                     = $v->hsub;    //"04047"
        //             @$hsub_name                = $v->hsub_name;   //"รพ.สต.แดงสว่าง"
        //             @$subinscl_name            = $v->subinscl_name; //"ช่วงอายุ 12-59 ปี"
        //             @$primary_amphur_name      = $v->primary_amphur_name;  // อำเภอ  "โพนทอง"
        //             @$primary_moo              = $v->primary_moo;    //หมู่ที่ 01
        //             @$primary_mooban_name      = $v->primary_mooban_name;  // ชื่อหมู่บ้าน  "หนองนกแก้ว"
        //             @$primary_tumbon_name      = $v->primary_tumbon_name;   //ชื่อตำบล   "สระนกแก้ว"
        //             @$primary_province_name    = $v->primary_province_name;  //ชื่อจังหวัด
        //         }
        //         $check_cid = DB::connection('mysql2')->table('patient')->where('cid','=',$collection['pid'])->count();
        //         $check_hcode = DB::connection('mysql')->table('orginfo')->where('orginfo_id','=','1')->first();
        //         if ($check_cid > 0) {
        //                     $data_patient_ = DB::connection('mysql2')->select(' 
        //                                 SELECT p.hn ,pe.pttype_expire_date as expiredate ,pe.pttype_hospmain as hospmain ,pe.pttype_hospsub as hospsub 
        //                                 ,p.pttype,pt.name as ptname,pt.hipdata_pttype, pe.pttype_no as pttypeno ,pe.pttype_begin_date as begindate,p.cid,p.hcode,p.last_visit,p.hometel
        //                                 ,p.bloodgrp,p.addrpart,p.informname,p.informrelation,p.informtel,p.fathername,p.fatherlname
        //                                 ,p.father_cid,p.mathername,p.motherlname,p.mother_cid,p.spsname,p.spslname
        //                                 ,h.chwpart,h.amppart,h.tmbpart,h.po_code
        //                                 FROM patient p 
        //                                 LEFT OUTER JOIN person pe ON pe.patient_hn = p.hn 
        //                                 LEFT OUTER JOIN pttype pt ON pt.pttype = p.pttype
        //                                 LEFT OUTER JOIN hospcode h ON h.chwpart = p.chwpart AND h.amppart = p.amppart AND h.tmbpart = p.tmbpart
        //                                 WHERE p.cid = "'.$collection['pid'].'"
        //                                 GROUP BY p.hn
        //                     ');
        //                     foreach ($data_patient_ as $key => $value) {
        //                         $pids          = $value->cid;
        //                         $hcode         = $value->hcode;
        //                         $hn            = $value->hn;
        //                         $last_visit    = $value->last_visit;
        //                         $hometel       = $value->hometel;
        //                         $chwpart       = $value->chwpart;
        //                         $amppart       = $value->amppart;
        //                         $tmbpart       = $value->tmbpart;
        //                         $addrpart       = $value->addrpart;
        //                         $po_code       = $value->po_code;
        //                         $bloodgrp      = $value->bloodgrp;

        //                         $informname      = $value->informname;
        //                         $informrelation  = $value->informrelation;
        //                         $informtel       = $value->informtel;
        //                         $fathername      = $value->fathername;
        //                         $fatherlname     = $value->fatherlname;
        //                         $father_cid      = $value->father_cid;
        //                         $mathername      = $value->mathername;
        //                         $motherlname     = $value->motherlname;
        //                         $mother_cid      = $value->mother_cid;
        //                         $spsname         = $value->spsname;
        //                         $spslname        = $value->spslname;
        //                         $ptname          = $value->ptname;
        //                         $hipdata_pttype  = $value->hipdata_pttype;
        //                         $pttype_s        = $value->pttype;

        //                     }
                           
        //                     $check_pttype = DB::connection('mysql10')->table('pttype')->where('hipdata_pttype','=',$subinscl)->where('pttype','=',$pttype_s)->get();


        //         } else {
        //                 $pids            = $collection['pid'];
        //                 $hcode           = $check_hcode->orginfo_code;
        //                 $hn              = '';
        //                 $last_visit      = '';
        //                 $hometel         = '';
        //                 $chwpart         = $primary_province_name;
        //                 $amppart         = $primary_amphur_name;
        //                 $tmbpart         = $primary_tumbon_name;
        //                 $addrpart        = '';
        //                 $po_code         = '';
        //                 $bloodgrp        = '';
        //                 $informname      = '';
        //                 $informrelation  = '';
        //                 $informtel       = '';
        //                 $fathername      = '';
        //                 $fatherlname     = '';
        //                 $father_cid      = '';
        //                 $mathername      = '';
        //                 $motherlname     = '';
        //                 $mother_cid      = '';
        //                 $spsname         = '';
        //                 $spslname        = '';
        //                 $ptname          = '';
        //                 $hipdata_pttype  = '';
        //                 $pttype_s          = '';

        //                 // $check_pttype = DB::connection('mysql10')->table('pttype')->where('hipdata_pttype','=',$subinscl)->get();
                    
        //         }
                
                
        //         // dd($check_pttype);
        //         // $check_pttype = DB::connection('mysql10')->table('pttype')->where('hipdata_pttype','=',$subinscl)->where('pttype','=',$pttype)->get();
        //         // $data['check_pttype'] = DB::connection('mysql10')->table('pttype')->where('hipdata_pttype','=',$subinscl)->get();
        //         // dd($hcode);
        //         $year = substr(date("Y"), 2) + 43;
        //         $mounts = date('m');
        //         $day = date('d');
        //         $time = date("His");
        //         $vn = $year . '' . $mounts . '' . $day . '' . $time;
        //         $time_s = date("H:i:s");

        //         $date = date('Y-m-d');
        //         // dd($vn);OK
        //         $getvn_stat =  DB::connection('mysql10')->select('select * from vn_stat limit 2');
        //         $get_ovst =  DB::connection('mysql10')->select('select * from ovst limit 2');
        //         $get_opdscreen =  DB::connection('mysql10')->select('select * from opdscreen limit 2');
        //         $get_ovst_seq =  DB::connection('mysql10')->select('select * from ovst_seq limit 2');
        //         $get_spclty =  DB::connection('mysql10')->select('select * from spclty');
        //         $data['ovstist'] =  DB::connection('mysql10')->select('select * from ovstist');
        //         $data['spclty'] =  DB::connection('mysql10')->select('select * from spclty');
        //         $data['kskdepartment'] =  DB::connection('mysql10')->select('select * from kskdepartment');
        //         $data['pt_priority'] =  DB::connection('mysql10')->select('select * from pt_priority order by id');
        //         $data['pt_walk'] =  DB::connection('mysql10')->select('select * from pt_walk');
        //         $data['pt_subtype'] =  DB::connection('mysql10')->select('select * from pt_subtype order by pt_subtype');
        //         $data['pname'] =  DB::connection('mysql10')->select('select * from pname order by name');
        //         $data['marrystatus'] =  DB::connection('mysql10')->select('select code,name from marrystatus');
        //         $data['nationality'] =  DB::connection('mysql10')->select(' select nationality as code,name from nationality order by nationality desc');
        //         $data['thaiaddress_provine'] =  DB::connection('mysql10')->select('select chwpart,name from thaiaddress WHERE codetype="1"');
        //         $data['thaiaddress_amphur'] =  DB::connection('mysql10')->select('select amppart,name from thaiaddress WHERE codetype="2"');
        //         $data['thaiaddress_tumbon'] =  DB::connection('mysql10')->select('select tmbpart,name from thaiaddress WHERE codetype="3"');
        //         $data['thaiaddress_po_code'] =  DB::connection('mysql10')->select('SELECT chwpart,amppart,tmbpart,po_code FROM hospcode WHERE po_code <>"" GROUP BY po_code');
        //         $data['blood_group'] =  DB::connection('mysql10')->select('select name from blood_group order by name');
        //         $data['informrelation_list'] =  DB::connection('mysql10')->select('select name from informrelation_list');
        //         $data['religion'] =  DB::connection('mysql10')->select('SELECT * FROM religion');
        //         $data['occupation'] =  DB::connection('mysql10')->select('SELECT * FROM occupation');
        //         $data_patient =  DB::connection('mysql10')->select('SELECT pttype FROM patient WHERE cid = "'.$collection['pid'].'" ');
        //         $data['pttype'] =  DB::connection('mysql10')->select('SELECT * FROM pttype');

        //         foreach ($data_patient as $key => $value_pat) {
        //             $check_pttype_ = DB::connection('mysql10')->table('pttype')->where('hipdata_pttype','=',$subinscl)->where('pttype','=',$value_pat->pttype)->first();
        //         }
        //         $ori_pttype  = $check_pttype_->pttype;
        //         // dd($ori_pttype);
        //         // $data['thaiaddress_provinces'] =  DB::connection('mysql10')->select(' select * from thaiaddress_provinces');
        //         // $data['thaiaddress_amphures'] =  DB::connection('mysql10')->select(' select * from thaiaddress_amphures');
        //         // $data['thaiaddress_districts'] =  DB::connection('mysql10')->select(' select * from thaiaddress_districts');
                

        //         //ที่เก็บรูปภาพ
        //         $data['patient_image'] =  DB::connection('mysql10')->select('select * from patient_image where image_name = "OPD" limit 100');
        //         // dd($hn);
        //         if ($hn == '') {
        //             $ovst_key = '';
        //         } else {
        //              ///// เจน  ovst_key  จาก Hosxp
        //             // $getovst_key_ = Http::get('https://cloud4.hosxp.net/api/ovst_key?Action=get_ovst_key&hospcode="' . $hcode . '"&vn="' . $vn . '"&computer_name=abcde&app_name=AppName&fbclid=IwAR2SvX7NJIiW_cX2JYaTkfAduFqZAi1gVV7ftiffWPsi4M97pVbgmRBjgY8')->collect();
        //             // $output5 = Arr::sort($getovst_key_);
        //             // $ovst_key = $output5['result']['ovst_key'];
        //             $ovst_key = '';
        //         }
                 
        //         ///// เจน  hos_guid  จาก Hosxp
        //         $data_key = DB::connection('mysql10')->select('SELECT uuid() as keygen');
        //         $output4 = Arr::sort($data_key);
        //         foreach ($output4 as $key => $value_) {
        //             $hos_guid = $value_->keygen;
        //         }

                
              
        //         // foreach ($output5 as $key => $value_ovst_key) { 
        //         //     $ovst_key = $value_ovst_key->ovst_key; 
        //         // }
        //         // dd($cardid);
        //         return view('authen.authen_main', $data, [
        //             'smartcard'                  =>  $smartcard,
        //             'cardcid'                    =>  $cardcid,
        //             'smartcardcon'               =>  $smartcardcon,
        //             'hometel'                    =>  $hometel,
        //             'vn'                         =>  $vn,
        //             'hn'                         =>  $hn,
        //             'chwpart'                    =>  $chwpart,
        //             'amppart'                    =>  $amppart,
        //             'tmbpart'                    =>  $tmbpart,
        //             'po_code'                    =>  $po_code,
        //             'bloodgrp'                   =>  $bloodgrp,
        //             'addrpart'                   =>  $addrpart,
        //             'last_visit'                 =>  $last_visit,
        //             'hcode'                      =>  $hcode,
        //             'hos_guid'                   =>  $hos_guid,
        //             'ovst_key'                   =>  $ovst_key,
        //             'time'                       =>  $time,
        //             'collection1'                => $collection['pid'],
        //             'collection2'                => $collection['fname'],
        //             'collection3'                => $collection['lname'],
        //             'collection4'                => $collection['birthDate'],
        //             'collection5'                => $collection['transDate'],
        //             'collection6'                => $collection['mainInscl'],
        //             'collection7'                => $collection['subInscl'],
        //             'collection8'                => $collection['age'],
        //             'collection9'                => $collection['checkDate'],
        //             'collection10'               => $collection['correlationId'],
        //             'collection11'               => $collection['checkDate'],
        //             'collection12'               => $collection['image'],
        //             'collection13'               => $collection['sex'],
        //             'collection14'               => $collection['nation'],
        //             'collection15'               => $collection['titleName'],
        //             'time_s'                     => $time_s,
        //             'date'                       => $date,
        //             'primary_moo'                => $primary_moo ,
        //             'primary_tumbon_name'        => $primary_tumbon_name ,
        //             'primary_amphur_name'        => $primary_amphur_name ,
        //             'primary_province_name'      => $primary_province_name ,
        //             // 'check_pttype'               => $check_pttype, 
        //             'informname'                 =>$informname,
        //             'informrelation'             =>$informrelation,
        //             'informtel'                  =>$informtel ,
        //             'fathername'                 =>$fathername ,
        //             'fatherlname'                =>$fatherlname ,
        //             'father_cid'                 =>$father_cid,
        //             'mathername'                 =>$mathername,
        //             'motherlname'                =>$motherlname,
        //             'mother_cid'                 =>$mother_cid,
        //             'spsname'                    =>$spsname,
        //             'spslname'                   =>$spslname,
        //             'ptname'                     =>$ptname,
        //             'hipdata_pttype'             =>$hipdata_pttype,
        //             'subinscl'                   =>$subinscl,
        //             'ori_pttype'                 =>$ori_pttype,
        //             'pttype_s'                   =>$pttype_s
        //         ]);
        //     }
        // }
    }
    public function authen_index(Request $request)
    {
        // $ip = $request()->ip();
        $ip = $request->ip();
        $collection = Http::get('http://' . $ip . ':8189/api/smartcard/read?readImageFlag=true')->collect();
        $patient =  DB::connection('mysql10')->select('select cid,hometel from patient limit 10');

        // $terminals = Http::get('http://'.$ip.':8189/api/smartcard/terminals')->collect();
        // $cardcid = Http::get('http://'.$ip.':8189/api/smartcard/read')->collect();
        // $cardcidonly = Http::get('http://'.$ip.':8189/api/smartcard/read-card-only')->collect();
        return view('authen.authen_index', [
            'collection1'  => $collection['pid'],
            'collection2'  => $collection['fname'],
            'collection3'  => $collection['lname'],
            'collection4'  => $collection['birthDate'],
            'collection5'  => $collection['transDate'],
            'collection6'  => $collection['mainInscl'],
            'collection7'  => $collection['subInscl'],
            'collection8'  => $collection['age'],
            'collection9'  => $collection['checkDate'],
            'collection10' => $collection['correlationId'],
            'collection11' => $collection['checkDate'],

            'collection'   => $collection,
            'patient'      => $patient
        ]);
    }
    public function authencode(Request $request)
    {
        // $category = $request->input('category');
        // $url = "http://localhost:8189/api/nhso-service/confirm-save/$request->pid";
            // [
            //       'pid'              =>  $request->pid,
            //      'claimType'        =>  $request->claimType,
            //        'mobile'           =>  $request->mobile,
            //       'correlationId'    =>  $request->correlationId,
            //      'hn'               =>  $request->hn,
            //       'hcode'            =>  $request->hcode
            // ]
      
        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, $url);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // $result = curl_exec($ch);
        // if ($result === false) {
        //     $error = curl_error($ch);
        //     curl_close($ch);
        //     // return view('inventory.client')->with('error', $error);
        // }
        // curl_close($ch);
        // $inventory = json_decode($result);
        // $ip = $request->ip(); 
        // $authen = Http::post(
        //     "http://localhost:8189/api/nhso-service/confirm-save/",
        //     [
        //         'pid'              =>  $request->pid,
        //         'claimType'        =>  $request->claimType,
        //         'mobile'           =>  $request->mobile,
        //         'correlationId'    =>  $request->correlationId,
        //         'hn'               =>  $request->hn,
        //         'hcode'            =>  $request->hcode
        //     ]
        // );
        
        // $response = Http::withHeaders([ 
        //     'User-Agent:<platform>/<version> <10978>',
        //     'Accept' => 'application/json',
        // ])->post('http://localhost:8189/api/nhso-service/confirm-save/', [
        //     'pid'              =>  $request->pid,
        //     'claimType'        =>  $request->claimType,
        //     'mobile'           =>  $request->mobile,
        //     'correlationId'    =>  $request->correlationId,
        //     'hn'               =>  $request->hn,
        //     'hcode'            =>  $request->hcode
        // ]);  
        // curl -X 'POST' \
        //     'http://localhost:8189/api/nhso-service/confirm-save' \
        //     -H 'accept: */*' \
        //     -H 'Content-Type: application/json' \
        //     -d '{
        //     "pid": "1119902562806",
        //     "claimType": "PG0060001",
        //     "mobile": "string",
        //     "correlationId": "63a369bb-5771-4335-9427-e3156ef41bfc",
        //     "hn": "string",
        //     "hcode": "string"
        //     }'
        // $fame_send = curl_init();
        // 'User-Agent:<platform>/<version><10978>' 
        $postData_send =  [
                    'pid'              =>  $request->pid,
                    'claimType'        =>  $request->claimType,
                    'mobile'           =>  $request->mobile,
                    'correlationId'    =>  $request->correlationId,
                    'hn'               =>  $request->hn,
                    'hcode'            =>  $request->hcode
        ];
        // // 'Authorization : Bearer '.$token,
        $headers_send  = [            
            'Content-Type: application/json'           
                         
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"http://localhost:8189/api/nhso-service/confirm-save/");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData_send, JSON_UNESCAPED_SLASHES));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_send);

        $server_output     = curl_exec ($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        $content = $server_output;
        $result = json_decode($content, true);
        // dd($result);
        // #echo "<BR>";
        // @$status = $result['status'];
        // #echo "<BR>";
        // @$message = $result['message'];

  
        Patient::where('cid', $request->pid)
            ->update([
                'hometel'    => $request->mobile
            ]); 
     
        return response()->json([
            'status'     => '200'
        ]);


        // $authen = Http::post("http://localhost:8189/api/nhso-service/save-as-draft/",[
        //     'pid'              =>  "pid",
        //     'claimType'        =>  "claimType",
        //     'mobile'           =>  "mobile",
        //     'correlationId'    =>  "correlationId",
        //     'hcode'            =>  "hcode"
        // ]);
        // $authen = new Authencode;
        // $authen->pid = $req->pid;
        // $authen->claimType = $req->claimType;
        // $authen->mobile = $req->mobile;
        // $authen->correlationId = $req->correlationId;
        // $authen->hcode = $req->hcode;

        // $result = $authen->save();

        // if ($result) {
        //     return ["result" => "Data Save success"];
        // } else {
        //     return ["result" => "Data Save Fail"];
        // }

    }

    public function authencode_patient_save(Request $request)
    {       
        $hos_guid                  = $request->hos_guid_p;
        $pname                     = $request->pname_p;
        $fname                     = $request->fname_p;
        $lname                     = $request->lname_p;
        $cid                       = $request->cid_p;
        $marrystatus               = $request->marrystatus_p;
        $citizenship               = $request->citizenship_p;
        $nationality               = $request->nationality_p;
        $sex                       = $request->sex_p;
        $addrpart                  = $request->addrpart_p;
        $moopart                   = $request->moopart_p;
        $hometel                   = $request->hometel_p;
        $bloodgrp                  = $request->bloodgrp_p;
        $chwpart                   = $request->chwpart_p;
        $amppart                   = $request->amppart_p;
        $tmbpart                   = $request->tmbpart_p;
        $po_code                   = $request->po_code_p;
        $hcode                     = $request->hcode_p;
        $lang                      = $request->lang_p;
        $country_p                 = $request->country_p;
        $informname                = $request->informname_p;
        $informrelation            = $request->informrelation_p;
        $fathername                = $request->fathername_p;
        $fatherlname               = $request->fatherlname_p;
        $mathername                = $request->mothername_p;
        $motherlname               = $request->motherlname_p; 
        $spsname_p                 = $request->spsname_p;
        $spslname_p                = $request->spslname_p;
        $father_cid                = $request->father_cid_p;
        $mother_cid                = $request->mother_cid_p;
        $religion                  = $request->religion_p;
        $occupation                = $request->occupation_p;
        $informtel                 = $request->informtel_p;
        $pttype                    = $request->pttype_p;
        
        // dd($amppart);

        $data['thaiaddress_provine'] =  DB::connection('mysql10')->select('select chwpart,name from thaiaddress WHERE codetype="1"');
        $data['thaiaddress_amphur'] =  DB::connection('mysql10')->select('select amppart,name from thaiaddress WHERE codetype="2"');
        $data['thaiaddress_tumbon'] =  DB::connection('mysql10')->select('select tmbpart,name from thaiaddress WHERE codetype="3"');

        $chwpart_ =  DB::connection('mysql10')->table('thaiaddress')->where('codetype','=','1')->where('chwpart','=',$chwpart)->first(); 
        $amphur_ =  DB::connection('mysql10')->table('thaiaddress')->where('codetype','=','2')->where('chwpart','=',$chwpart)->where('amppart','=',$amppart)->first(); 
        $tumbon_ =  DB::connection('mysql10')->table('thaiaddress')->where('codetype','=','3')->where('chwpart','=',$chwpart)->where('amppart','=',$amppart)->where('tmbpart','=',$tmbpart)->first(); 

        // dd($tumbon_->name);
        $informaddr_               = $addrpart.' หมู่ '.$moopart.' ต.'.$tumbon_->name.' อ.'.$amphur_->name.' จ.'.$chwpart_->name;            
        
        $birthday_                  = $request->birthDate_p; 
        $ye      = substr($birthday_, 0, 4)-543;
        $mo      = substr($birthday_, 4, 2);
        $day     = substr($birthday_, 6, 2);            
        $birthday = $ye.'-'.$mo.'-'.$day;
     
        $max_hn          = Patient::whereNotIn('hn',['1111111', '2222222','3333333','4444444'])->max('hn')+1;
        $date            = date('Y-m-d');
        $reg_time        = date("H:i:s");
        $last_update     = date('Y-m-d H:i:s');
        Patient::insert([
            'hos_guid'             => '{'.$hos_guid.'}',
            'hn'                   => $max_hn,
            'pname'                => $pname,
            'fname'                => $fname,
            'lname'                => $lname,
            'cid'                  => $cid,
            'marrystatus'          => $marrystatus,
            'citizenship'          => $citizenship,
            'nationality'          => $nationality,
            'sex'                  => $sex,
            'addrpart'             => $addrpart,
            'moopart'              => $moopart,
            'informname'           => $informname,
            'informrelation'       => $informrelation,
            'fathername'           => $fathername,
            'fatherlname'          => $fatherlname, 
            'father_cid'           => $father_cid, 
            'mathername'           => $mathername,
            'motherlname'          => $motherlname, 
            'mother_cid'           => $mother_cid, 
            'spsname'              => $spsname_p,
            'spslname'             => $spslname_p,
            'hometel'              => $hometel,
            'informaddr'           => $informaddr_,
            'bloodgrp'             => $bloodgrp,
            'chwpart'              => $chwpart,
            'amppart'              => $amppart,
            'tmbpart'              => $tmbpart,
            'po_code'              => $po_code,
            'hcode'                => $hcode,
            'birthday'             => $birthday_,
            'firstday'             => $date,
            'religion'             => $religion,
            'occupation'           => $occupation,
            'informtel'            => $informtel,
            'pttype'               => $pttype,
            'last_update'          => $last_update,
            'country'              => $country_p,
            'death'                => 'N',
            'last_visit'           => $date,
            'reg_time'             => $reg_time,
            'lang'                 => $lang,
           
        ]);
        return response()->json([
            'status'     => '200',
        ]);
    }

    public function authencode_visit_save(Request $request)
    {
        $hos_guid       = $request->hos_guid;
        $ovst_key       = $request->ovst_key;
        $vn             = $request->vn;
        $hcode          = $request->hcode;
        $pid            = $request->pid;
        $mainInscl      = $request->mainInscl; //สิทธิหลัก
        $subInscl       = $request->subInscl;   //สิทธิ์ย่อย
        $claimType      = $request->claimType;
        $claimType2     = $request->claimType2;
        $claimType3     = $request->claimType3;
        
        if ($claimType != '') {
            $claimType_ = $request->claimType;
          }else if ($claimType2 != '') {
            $claimType_ = $request->claimType2;
        } else {
            $claimType_ = $request->claimType3;
        }
                
        $mobile         = $request->mobile;
        $hn             = $request->hn;
        $main_dep_queue = $request->main_dep_queue; //ส่งต่อไปยัง
        $spclty         = $request->spclty;  //แผนก
        $pt_subtype     = $request->pt_subtype;  //ประเภท
        $ovstist        = $request->ovstist;  //ประเภทการมา
        $pt_priority    = $request->pt_priority;  //ความเร่งด่วน
        $pt_walk        = $request->pt_walk; //สภาพผู้ป่วย
        $cc             = $request->cc; //อาการที่มา 
        $time           = substr($request->vn,6,6);
        $vstdate = date('Y-m-d');
        $outtime = date("His");
        $datetime = date('Y-m-d H:i:s');




        // dd($hos_guid);OK    
        $data_patient_ = DB::connection('mysql10')->select(' 
                SELECT p.hn
                ,pe.pttype_expire_date as expiredate
                ,pe.pttype_hospmain as hospmain
                ,pe.pttype_hospsub as hospsub 
                ,p.pttype
                ,pe.pttype_no as pttypeno
                ,pe.pttype_begin_date as begindate,pe.cid
                FROM hos.patient p 
                LEFT OUTER JOIN hos.person pe ON pe.patient_hn = p.hn 
                WHERE p.cid = "' . $pid . '"
        ');
        foreach ($data_patient_ as $key => $value) {
            $expiredate    = $value->expiredate;
            $hospmain      = $value->hospmain;
            $hospsub       = $value->hospsub;
            $pttype        = $value->pttype;
            $pttypeno      = $value->pttypeno;
            $begindate     = $value->begindate;
            // $cid           = $value->cid;
        }
        $token_data = DB::connection('mysql10')->select('SELECT * FROM nhso_token ORDER BY update_datetime desc limit 1');
        foreach ($token_data as $key => $value) {
            $cid_    = $value->cid;
            $token_  = $value->token;
        }
        $client = new SoapClient(
            "http://ucws.nhso.go.th/ucwstokenp1/UCWSTokenP1?wsdl",
            array("uri" => 'http://ucws.nhso.go.th/ucwstokenp1/UCWSTokenP1?xsd=1', "trace" => 1, "exceptions" => 0, "cache_wsdl" => 0)
        );
        $params = array(
            'sequence' => array(
                "user_person_id" => "$cid_",
                "smctoken"       => "$token_",
                "person_id"      => "$pid"
            )
        );
        $contents = $client->__soapCall('searchCurrentByPID', $params);
        foreach ($contents as $v) {
            @$status           = $v->status;
            @$maininscl        = $v->maininscl;
            @$startdate        = $v->startdate;
            @$hmain            = $v->hmain;
            @$subinscl         = $v->subinscl;
            @$person_id_nhso   = $v->person_id;
            @$hmain_op         = $v->hmain_op;  //"10978"
            @$hmain_op_name    = $v->hmain_op_name;  //"รพ.ภูเขียวเฉลิมพระเกียรติ"
            @$hsub             = $v->hsub;    //"04047"
            @$hsub_name        = $v->hsub_name;   //"รพ.สต.แดงสว่าง"
            @$subinscl_name    = $v->subinscl_name; //"ช่วงอายุ 12-59 ปี"
        }

        Vn_insert::insert([
            'vn'         => $vn,
            'hos_guid'   => $hos_guid 
        ]);
        $check_ptt = Pttypehistory::where('hn',$hn)->count();
        if ($check_ptt > 0) {
            # code...
        } else {
            Pttypehistory::insert([ 
                'hn'                => $hn,
                'expiredate'        => $expiredate,
                'hospmain'          => $hospmain,
                'hospsub'           => $hospsub,
                'pttype'            => $pttype,
                'pttypeno'          => $pttypeno,
                'begindate'         => $begindate, 
                'hos_guid'          => $hos_guid 
            ]);
        }
        
       
        $max_q = Ovst::max('oqueue')+1;
        Ovst::insert([
            'hos_guid'          => $hos_guid,
            'vn'                => $vn,
            'hn'                => $hn,
            'vstdate'           => $vstdate,
            'vsttime'           => $time,
            'hospmain'          => $hospmain,
            'hospsub'           => $hospsub,
            'oqueue'            => $max_q,
            'ovstist'           => $ovstist,
            // 'ovstost'           => $value->begindate,
            'pttype'            => $pttype,
            'pttypeno'          => $pttypeno,
            'spclty'            => $spclty,
            'hcode'             => $hcode,
            // 'last_dep'          => $value->begindate,
            'pt_subtype'        => $pt_subtype,
            'main_dep_queue'    => $main_dep_queue,
            'visit_type'        => 'I',
            'node_id'           => '',
            'waiting'           => 'Y',
            'has_insurance'     => 'N',
            // 'staff'             => $value->staff,
            'pt_priority'       => $pt_priority,
            'ovst_key'          => $ovst_key,
        ]);

        Ptdepart::insert([ 
            'vn'                => $vn,
            // 'depcode'           => $depcode,
            'hn'                => $hn,
            'intime'            => $time,
            // 'outdepcode'        => $outdepcode,
            'outtime'           => $outtime,
            // 'staff'             => $staff, 
            'outdate'           => $vstdate,
            'hos_guid'          => $hos_guid, 
        ]);

        Service_time::insert([ 
            'vn'                => $vn, 
            'hn'                => $hn,
            'vstdate'           => $vstdate ,
            'vsttime'           => $time,
            //'service3'        => $service3,
             //'staff'          => $staff, 
            'last_send_time'    => $datetime,
           //'service3_dep'        => $service3_dep,
            
        ]);

        Visit_pttype::insert([ 
            'vn'                => $vn, 
            'pttype'            => $pttype,
            'begin_date'        => $begindate ,
            'expire_date'       => $expiredate,
            'hospmain'          => $hospmain,
            'hospsub'           => $hospsub, 
            'pttypeno'          => $pttypeno,
            'hos_guid'          => $hos_guid,
            'claim_code'        => $claimType_,
           'pttype_number'      => '1',
             'contract_id'      => '0',
        ]);

        // dd($contents);
        return response()->json([
            'status'     => '200',
        ]);
        
    }
    // จังหวัด
    function fetch_province(Request $request)
    { 
            // =  DB::connection('mysql10')->select(' select chwpart,name from thaiaddress WHERE codetype="1"');
            $id = $request->get('select');
            $result=array();
            // $query=DB::connection('mysql10')->select('select chwpart,name,amppart from thaiaddress WHERE codetype IN("1","2")');
            $query= DB::connection('mysql10')->table('thaiaddress')
            // ->join('hrd_amphur','hrd_province.ID','=','hrd_amphur.PROVINCE_ID')
            // ->select('hrd_amphur.AMPHUR_NAME','hrd_amphur.ID')
            ->where('chwpart',$id)
            ->where('codetype','=','2')
            // ->groupBy('hrd_amphur.AMPHUR_NAME','hrd_amphur.ID')
            ->get();

            $output='<option value="">--Choose--</option> ';
            // $output=''; 
            foreach ($query as $row){ 
                    $output.= '<option value="'.$row->amppart.'">'.$row->name.'</option>';
            } 
            echo $output; 
    }
    // อำเภอ
    function fetch_amphur(Request $request)
    { 
            $id          = $request->get('select');
            $province    = $request->get('province');
            $result=array();
            $query= DB::connection('mysql10')->table('thaiaddress')
            // ->join('hrd_amphur','hrd_province.ID','=','hrd_amphur.PROVINCE_ID')
            // ->select('hrd_amphur.AMPHUR_NAME','hrd_amphur.ID')
            ->where('chwpart',$province)
            ->where('amppart',$id)
            ->where('codetype','=','3')
            ->get();
            $output='<option value="">--Choose--</option> ';
            
            foreach ($query as $row){

                    $output.= '<option value="'.$row->tmbpart.'">'.$row->name.'</option>';
            } 
            echo $output; 
    }

    function fetch_tumbon(Request $request)
    { 
            $id          = $request->get('select');
            $amphur    = $request->get('amphur');
            $province    = $request->get('province');
            $result=array();
            // $query= DB::connection('mysql10')->table('hospcode') 
            // ->where('chwpart',$province)
            // ->where('amppart',$amphur)
            // ->where('tmbpart',$id)
            // // ->where('codetype','=','3')
            // ->groupBy('po_code');
            // $output='<input value=""></>';

            $query = DB::connection('mysql10')->select('SELECT chwpart,amppart,tmbpart,po_code FROM hospcode WHERE chwpart ="'.$province.'" AND amppart ="'.$amphur.'" AND tmbpart ="'.$id.'" AND po_code <> "-" GROUP BY po_code');
            // $output=' ';
            $output='<option value="">--Choose--</option> ';
            foreach ($query as $row){
                $output.= '<option value="'.$row->po_code.'">'.$row->po_code.'</option>';
                    // $output.= '<input value="'.$row->pocode.'" class="form-control" >'.$row->pocode.'</>';
            } 
            echo $output; 
    }
    


}
