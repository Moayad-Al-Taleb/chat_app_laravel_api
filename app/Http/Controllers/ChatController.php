<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetChatRequest;
use App\Http\Requests\StoreChatRequest;
use App\Models\Chat;
use Illuminate\Http\Request;

class ChatController extends Controller
{

    /**
     * @OA\Get(
     * path="/api/chat",
     * summary="Retrieve a list of chats for the authenticated user.",
     * description="This endpoint retrieves a list of chats for the authenticated user, including both private and public chats.",
     * operationId="getUserChats",
     * tags={"Chats"},
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

    public function index(GetChatRequest $request)
    {
        // Validate the request using the GetChatRequest class
        $data = $request->validated();

        // Set a default value for $isPrivate
        $isPrivate = 1;

        // Check if the request contains 'is_private' parameter
        if ($request->has('is_private')) {
            // If it does, update $isPrivate with the value from the request
            $isPrivate = (int) $data['is_private'];
        }

        // Query the chats
        $chats = Chat::where('is_private', $isPrivate)
            ->hasParticipant(auth()->user()->id)
            ->whereHas('messages')
            ->with('lastMessage.user', 'participants.user')
            ->latest('updated_at')
            ->get();

        // Return a JSON response with the list of chats
        return $this->success($chats);
    }

    /**
     * @OA\Post(
     * path="/api/chat",
     * summary="Create or retrieve a chat between authenticated user and another user.",
     * description="This endpoint creates a new chat or retrieves an existing chat between the authenticated user and another user.",
     * operationId="storeChat",
     * tags={"Chats"},
     * security={ {"bearer": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Chat creation details",
     *         @OA\JsonContent(
     *             required={"other_user_id"},
     *             @OA\Property(property="other_user_id", type="integer", description="ID of the other user involved in the chat."),
     *         )
     *     ),
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

    public function store(StoreChatRequest $request)
    {
        // Prepare the necessary data for storing the message
        $data = $this->prepareStoreData($request);

        // Check if the user is trying to send a message to themselves
        if ($data['userId'] === $data['otherUserId']) {
            return $this->error('You can not create a chat with your own');
        }

        // Search for a previous chat between the users
        $previousChat = $this->getPreviousChat($data['otherUserId']);

        if ($previousChat === null) {
            // If there is no previous chat, create a new chat
            $chat = Chat::create($data['data']);
            // Add participants to the new chat
            $chat->participants()->createMany([
                [
                    'user_id' => $data['userId']
                ],
                [
                    'user_id' => $data['otherUserId']
                ]
            ]);

            // Reload the updated data for the chat
            $chat->refresh()->load('lastMessage.user', 'participants.user');
            return $this->success($chat);
        }

        // If there is a previous chat between the users, return it
        return $this->success($previousChat->load('lastMessage.user', 'participants.user'));
    }

    private function getPreviousChat(int $otherUserId)
    {
        // Get the ID of the current user
        $userId = auth()->user()->id;

        // Search for a previous chat between the users
        return Chat::where('is_private', 1)
            ->whereHas('participants', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->whereHas('participants', function ($query) use ($otherUserId) {
                $query->where('user_id', $otherUserId);
            })
            ->first();

    }

    private function prepareStoreData(StoreChatRequest $request)
    {
        // Check and extract the required data from the request
        $data = $request->validated();
        $otherUserId = (int) $data['user_id'];
        unset($data['user_id']);
        // Add the ID of the current user as the creator of the message
        $data['created_by'] = auth()->user()->id;

        return [
            'otherUserId' => $otherUserId,
            'userId' => auth()->user()->id,
            'data' => $data,
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/chat/{chat}",
     *     summary="Retrieve details of a specific chat.",
     *     description="This endpoint retrieves details of a specific chat, including its last message and participants.",
     *     operationId="showChat",
     *     tags={"Chats"},
     *     security={ {"bearer": {} }},
     *     @OA\Parameter(
     *         name="chat",
     *         in="path",
     *         description="ID of the chat to retrieve.",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="chat", type="object"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Chat not found."),
     *         )
     *     ),
     * )
     */
    public function show(Chat $chat)
    {
        // Load relationships to eager load associated data
        $chat->load('lastMessage.user', 'participants.user');

        // Return a JSON response with the details of the chat
        return $this->success($chat);
    }
}
