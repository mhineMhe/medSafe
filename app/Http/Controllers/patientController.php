<?php

namespace App\Http\Controllers;

use App\model\AppointmentRequests;
use App\model\Doctors;
use App\model\Nationalities;
use App\model\Patient;
use App\model\Records;  
use App\model\Specialties;
use Illuminate\Http\Request;

class patientController extends Controller
{
    private $user_email;
    public $requests;
    public function __construct()
    {
        $this->middleware('guest', ['except' => 'logout']);
    }
    // joining two tables nationality/specialty and patient table to create nationality_id.
    public function nationality_specialty($j, $x)
    {
        $id = 0;
        $nationality_specialty;
        if ($j == 'nationalities') {
            $nationality_specialty = new Nationalities();
        } else if ($j == 'specialties') {
            $nationality_specialty = new Specialties();
        }
        $result = $nationality_specialty->get(['id', $j])->where($j, $x);
        $id = 1;
        if (count($result) >= 1) {
            foreach ($result as $a) {
                $id = $a['id'];
            }
        } else {
            $nationality_specialty::create([$j => $x]);
            $result = $nationality_specialty->get(['id', $j])->where($j, $x);
            foreach ($result as $a) {
                $id = $a['id'];
            }
        }
        return $id;
    }

// creating new user (Register page) could be patient or doctor;
    public function createUser(Request $request)
    {
        $id_nationality = $this->nationality_specialty('nationalities', $request->nationalityId);
        $data = $request->all();
        if ($request->specialtyId) {
            $id_specialty = $this->nationality_specialty('specialties', $request->specialtyId);
            $user = new Doctors($data);
            $user->specialtyId = $id_specialty;
            $user->nationalityId = $id_nationality;
            $user->save();
            $this->user_email = $request->email;
            $id = doctors::select('id')->where('email', $request->email)->get();
            $doc = doctors::find($id);
            $request->session()->put('user', $doc[0]->firstname." ".$doc[0]->lastname);
            $request->session()->put('doctorId', $id);
            $request->session()->put('userType', 'doctor');
            return redirect('/patients')->with('user', $request->session()->get('user'));
        } else {
            $user = new patient($data);
            $user->nationalityId = $id_nationality;
            $doctors = doctors::all();
            $user->save();
            $this->user_email = $request->email;
            $id = patient::select('id')->where('email', $request->email)->get();
            $patient = patient::find($id);
            $request->session()->put('user', $patient[0]->firstname." ".$patient[0]->lastname);
            $request->session()->put('patientId', $id);
            $request->session()->put('userType', 'patient');
            return view('patientDashboard', compact('doctors'))->with('user', $request->session()->get('user'));
        }
    }


    public function retrieveUser()
    {

        $n = new Patient();
        $users = Patient::with('nationality')->get();
        print_r($users . "<br><br>");
    }

    public function search_doctor(Request $request, $id)
    {
        $doctors = doctors::with('specialty')->where('id', $id)->get();
        return view('viewprofile', ['doctors'=>$doctors]);
    }

    public function retrieveDoctor(Request $request)
    {
        $doctors = doctors::all();
        return view('patientDashboard', compact('doctors'));
    }

    // search doctors by patient in the search bar (specialty).
    // show all doctors
    public function search_doctors_by_category(Request $request)
    {
        $specialty_doctors = Specialties::select('id')->where('specialties', $request->specialty)->get();
        if (count($specialty_doctors) >= 1) {
            $result = doctors::get()->where('specialtyId', $specialty_doctors[0]->id);
            $doctors = doctors::all();
            $all = array();
            foreach ($result as $a) {
                array_push($all, $a);
            }
            foreach ($doctors as $doctor) {
                foreach ($result as $a) {
                    if ($a['email'] == $doctor['email']) {
                        continue;
                    } else {
                        array_push($all, $doctor);
                    }
                }
            }
            $doctors = $all;
            return view('patientDashboard', compact('doctors'));
        } else {
            $doctors = doctors::all();
            return view('patientDashboard', compact('doctors'));
        }
    }

    // show all patients
    public function retrieve_all_patients()
    {
        return patient::all();
    }

    // find specific patient for patient profile
    function search(Request $request){
        $requests = [];
        $key_search = patient::where('firstname','like', '%' . $request->name . '%')->get(['id']);
        foreach($key_search as $patient){
            $req = AppointmentRequests::with('requestDoctor')->get()->where('patient_id', $patient->id);
            array_push($requests, $req);
            echo $req;
        }
        // return view('dashboard_doctor',compact('requests'));
    }

    function find_patient(Request $request){
        $AppointmentRequests = AppointmentRequests::find($request->id);
        $AppointmentRequests->Status = 1;
        $AppointmentRequests->save();
        $patient = AppointmentRequests::with('requestDoctor')->get()->where('id',$request->id);
        $links = session()->has('links') ? session('links') : [];
        $currentLink = request()->path(); // Getting current URI 
        array_unshift($links, $currentLink); // Putting it in the beginning of links array
        session(['links' => $links]); // Saving links array to the session
        return view('dashboard_doctor',compact('patient'));
        }


    // -----------------------DASHBOARD----------
    // function doctor_dashboard(){
    //     return $this->requests();
    // }

    function patient_dashboard(Request $request){
        $doctors = doctors::all();
        return view('patientDashboard', compact('doctors'))->with('user', $request->session()->get('user'));
    }


    // --------------------REQUEST----------
    
    // make appointment send request to doctor
    public function send_request(Request $request)
    {
        $patient_id = $request->session()->get('patientId');
        $request = new AppointmentRequests(['patient_id' => $patient_id[0]->id, 'doctor_id' => $request->doctor_id]);
        $request->message = 'hai';
        $request->save();
        return redirect('notify');
    }


    // ang request id iyang dawaton gikan sa front-end
    public function accept_request(Request $request)
    {
        $acceptRequest = AppointmentRequests::find($request->request_id);
        $acceptRequest->request = 1;
        $acceptRequest->save();
        return redirect('patients');
    }
    // GET ALL ACCEPTED REQUEST FROM DOCTOR
    function requests(Request $request){
        $doctor = doctors::find($request->session()->get('doctorId'));
        $requests = AppointmentRequests::with('requestDoctor')->get()->where('doctor_id',$doctor[0]->id);
        $this->requests = $requests;
        return view('dashboard_doctor',compact('requests'));
    }
    
    // GET ALL UNACCEPTED REQUEST FROM DOCTOR
    function unaccepted_request(Request $request){
        $doctor = $request->session()->get('doctorId');
        $requests = AppointmentRequests::with('requestDoctor')->get()->where('request',0)->where('doctor_id',$doctor[0]->id);
        return view('dashboard_doctor',compact('requests'));
    }
    // -------------------------------

    // make a record for a specific patient
    public function add_record(Request $request)
    {
        $record = new Records($request->all());
        $record->patient_id = $request->patient_id;
        $record->save();
    }

   

    // authenticate login
    public function login(Request $request)
    {
        session_start();
        $validate_email = patient::select('password')->where('email', $request->email)->get();
        if (count($validate_email) < 1) {
            $validate_email = doctors::select('password')->where('email', $request->email)->get();
            $id = doctors::select('id')->where('email', $request->email)->get();
            $user = doctors::find($id);
            if (count($validate_email) >= 1) {
                if ($validate_email[0]->password == $request->password) {
                    $this->user_email = $request->email;
                    $request->session()->put('user', $user[0]->firstname." ".$user[0]->lastname);
                    $request->session()->put('doctorId', $id);
                    $request->session()->put('userType', 'doctor');
                    return redirect('/patients')->with('user', $request->session()->get('user'));
                } else {
                    return redirect('/');
                }
            } else {
                return redirect('/');
            }
        } else {
            if ($validate_email[0]->password == $request->password) {
                $doctors = doctors::all();
                $this->user_email = $request->email;
                $validate_email = patient::select('password')->where('email', $request->email)->get();
                $id = patient::select('id')->where('email', $request->email)->get();
                $user = patient::find($id);
                $request->session()->put('user', $user[0]->firstname." ".$user[0]->lastname);
                $request->session()->put('patientId', $id);
                $request->session()->put('userType', 'patient');
                return view('patientDashboard', compact('doctors'))->with('user', $request->session()->get('user'));
            } else {
                return redirect('/');
            }
        }
    }
}
