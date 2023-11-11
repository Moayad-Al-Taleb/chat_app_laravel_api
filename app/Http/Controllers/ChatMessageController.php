<?php

namespace App\Http\Controllers;

use App\Events\NewMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetMessageRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Models\ChatMessage;
use Illuminate\Http\Request;

class ChatMessageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/chat_message",
     *     summary="Retrieve messages for a specific chat.",
     *     description="This endpoint retrieves messages for a specific chat, paginated and sorted by creation date.",
     *     operationId="getChatMessages",
     *     tags={"Messages"},
     *     security={ {"bearer": {} }},
     *     @OA\Parameter(
     *         name="chat_id",
     *         in="query",
     *         description="ID of the chat to retrieve messages from.",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for paginated results.",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *     ),
     *     @OA\Parameter(
     *         name="page_size",
     *         in="query",
     *         description="Number of messages per page. Defaults to 15 if not provided.",
     *         required=false,
     *         @OA\Schema(type="integer"),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="content", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="user", type="object"),
     *             ),
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
    public function index(GetMessageRequest $request)
    {
        // Retrieve validated data from the request
        $data = $request->validated();

        // Extract chat_id, page, and page_size from the validated data
        $chatId = $data['chat_id'];
        $currentPage = $data['page'];
        $pageSize = $data['page_size'] ?? 15; // If page_size is not provided, default to 15

        // Retrieve messages for the specified chat, eager loading the associated user, and paginate the results
        $messages = ChatMessage::where('chat_id', $chatId)
            ->with('user')
            ->latest('created_at')
            ->simplePaginate($pageSize, ['*'], 'page', $currentPage);

        // Return a JSON response containing the collection of messages
        return $this->success($messages->getCollection());
    }

    /**
     * @OA\Post(
     *     path="/api/chat_message",
     *     summary="Send a new chat message.",
     *     description="This endpoint allows the authenticated user to send a new chat message.",
     *     operationId="sendMessage",
     *     tags={"Messages"},
     *     security={ {"bearer": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Message creation details",
     *         @OA\JsonContent(
     *             required={"chat_id", "content"},
     *             @OA\Property(property="chat_id", type="integer", description="ID of the chat to send the message to."),
     *             @OA\Property(property="content", type="string", description="Content of the message."),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="content", type="string"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
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
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid data provided."),
     *             @OA\Property(property="errors", type="object"),
     *         )
     *     ),
     * )
     */
    public function store(StoreMessageRequest $request)
    {
        // Validate the incoming request using the StoreMessageRequest rules
        $data = $request->validated();

        // Assign the user_id from the authenticated user
        $data['user_id'] = auth()->user()->id;

        // Create a new chat message record in the database
        $chatMessage = ChatMessage::create($data);

        // Load the associated user for the created chat message to include user details
        $chatMessage->load('user');

        // Send notification to other users about the new chat message
        $this->sendNotificationToOther($chatMessage);

        // Return a JSON response indicating the success of the operation
        return $this->success($chatMessage, 'Message has been sent successfully.');
    }

    private function sendNotificationToOther(ChatMessage $chatMessage)
    {
        broadcast(new NewMessageSent($chatMessage))->toOthers();
    }

}
