<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/users",
     * summary="Retrieve a list of users (excluding the authenticated user) for the index page.",
     * description="This endpoint retrieves a list of users, excluding the authenticated user, for the index page.",
     * operationId="getUserList",
     * tags={"Users"},
     * security={ {"bearer": {} }},
     * @OA\Response(
     *    response=200,
     *    description="Successful operation",
     *    @OA\JsonContent(
     *       @OA\Property(property="users", type="object"),
     *    )
     * ),
     * @OA\Response(
     *    response=401,
     *    description="Unauthorized",
     *    @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Unauthenticated"),
     *    )
     * )
     * )
     */
    public function index()
    {
        $users = User::where('id', '!=', auth()->user()->id)->get();
        return $this->success($users);
    }



}



