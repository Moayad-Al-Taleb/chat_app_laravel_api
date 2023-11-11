<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\loginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/register",
     *     summary="Register a new user.",
     *     description="This endpoint registers a new user with the provided credentials.",
     *     operationId="registerUser",
     *     tags={"Users"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="User registration details",
     *         @OA\JsonContent(
     *             required={"name", "email", "password"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="secret123"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string", example="generated_token"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object"),
     *         )
     *     ),
     * )
     */

    public function register(RegisterRequest $request)
    {
        // Extract validated data from the request
        $data = $request->validated();

        // Hash the password
        $data['password'] = Hash::make($data['password']);

        // Extract name from the email address
        $data['name'] = strstr($data['email'], '@', true);

        // Create a new user
        $user = User::create($data);

        // Create and return the token
        $token = $user->createToken(User::USER_TOKEN);

        return $this->success([
            'user' => $user,
            'token' => $token->plainTextToken,
        ], 'User has been registered successfully.');
    }

    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Authenticate and log in a user.",
     *     description="This endpoint authenticates a user with the provided credentials and generates an access token.",
     *     operationId="loginUser",
     *     tags={"Users"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="User login details",
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="secret123"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string", example="generated_token"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid credentials."),
     *         )
     *     ),
     * )
     */

    public function login(LoginRequest $request)
    {
        // Check if the credentials are valid
        $isValid = $this->isValidCredential($request);
        if (!$isValid['success']) {
            return $this->error($isValid['message'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Get the authenticated user and create a token
        $user = $isValid['user'];
        $token = $user->createToken(User::USER_TOKEN);

        return $this->success([
            'user' => $user,
            'token' => $token->plainTextToken
        ], 'Login successful!');
    }


    private function isValidCredential(LoginRequest $request): array
    {
        $data = $request->validated();

        // Find the user by email
        $user = User::where('email', $data['email'])->first();

        if ($user === null) {
            return [
                'success' => false,
                'message' => 'Invalid credentials'
            ];
        }

        // Check if the password matches
        if (Hash::check($data['password'], $user->password)) {
            return [
                'success' => true,
                'user' => $user
            ];
        }

        return [
            'success' => false,
            'message' => 'Password does not match',
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/login-with-token",
     *     summary="Retrieve information about the authenticated user.",
     *     description="This endpoint retrieves information about the authenticated user using the provided access token.",
     *     operationId="loginWithToken",
     *     tags={"Users"},
     *     security={ {"bearer": {} }},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated"),
     *         )
     *     ),
     * )
     */

    public function loginWithToken()
    {
        return $this->success(auth()->user(), 'Login successful!');
    }


    /**
     * @OA\Get(
     *     path="/api/logout",
     *     summary="Revoke the current user's access token and log them out.",
     *     description="This endpoint revokes the current user's access token, effectively logging them out.",
     *     operationId="logoutUser",
     *     tags={"Users"},
     *     security={ {"bearer": {} }},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logout successful!"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated"),
     *         )
     *     ),
     * )
     */

    public function logout(Request $request)
    {
        // Delete the current access token
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, 'Logout successful!');
    }
}
