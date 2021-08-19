<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response as Download;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::orderBy('created_at', 'DESC')->get();
        return view('welcome', compact('users'));
    }

    public function store(Request $request)
    {
        //VALIDASI DATA YANG DIKIRIMKAN PENGGUNA
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'avatar' => 'required|image|mimes:jpg,jpeg,png'
        ]);

        //JIKA FILE TERSEDIA
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar'); //MAKA KITA GET FILENYA
            //BUAT CUSTOM NAME YANG DIINGINKAN, DIMANA FORMATNYA KALI INI ADALH EMAIL + TIME DAN MENGGUNAKAN ORIGINAL EXTENSION
            $filename = $request->email . '-' . time() . '.' . $file->getClientOriginalExtension();
            //UPLOAD MENGGUNAKAN CONFIG S3, DENGAN FILE YANG DIMASUKKAN KE DALAM FOLDER IMAGES
            //SECARA OTOMATIS AMAZON AKAN MEMBUAT FOLDERNYA JIKA BELUM ADA
            Storage::disk('s3')->put('images/' . $filename, file_get_contents($file));

            //SIMPAN INFORMASI USER KE DATABASE
            //DAN AVATAR YANG DISIMPAN HANYALAH FILENAME-NYA SAJA
            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'avatar' => $filename,
                'password' => bcrypt($request->password)
            ]);
            //REDIRECT KE HALAMAN YANG SAMA DAN BERIKAN NOTIFIKASI
            return redirect()->back()->with(['success' => 'Data Berhasil Disimpan']);
        }
        return redirect()->back()->with(['error' => 'Gambar Belum Dipilih']);
    }

    public function destroy($id)
    {
        $user = User::find($id); //AMBIL DATA USER BERDASARKAN ID
        Storage::disk('s3')->delete('images/' . $user->avatar); // HAPUS FILE YANG ADA DI S3, DI DALAM FOLDER IMAGES
        $user->delete(); //HAPUS DATA USER DARI DATABASE
        return redirect()->back()->with(['success' => 'Data Berhasil Dihapus']);
    }

    public function download(Request $request)
    {
        $url = 'images/' . $request->get('url');
        $urll = 'images/administrator@larashop.test-1629388252.JPG';

        // $headers = [
        //     'Content-Type'        => 'Content-Type: image/jpg',
        //     'Content-Disposition' => 'attachment; filename="' . $url . '"',
        // ];

        // return Download::make(Storage::disk('s3')->get($url), Response::HTTP_OK, $headers);

        return redirect(Storage::disk('s3')->temporaryUrl(
            $url,
            now()->addHour(),
            ['ResponseContentDisposition' => 'attachment']
        ));
    }
}
