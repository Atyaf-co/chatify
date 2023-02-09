<?php

namespace Chatify;

use App\Models\ChMessage as Message;
use App\Models\ChFavorite as Favorite;
use Illuminate\Support\Facades\Storage;
use Pusher\Pusher;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\File;

class ChatifyMessenger
{
    public $pusher;

    /**
     * Get max file's upload size in MB.
     *
     * @return int
     */
    public function getMaxUploadSize()
    {
        return config('chatify.attachments.max_upload_size') * 1048576;
    }

    public function __construct()
    {
        $this->pusher = new Pusher(
            config('chatify.pusher.key'),
            config('chatify.pusher.secret'),
            config('chatify.pusher.app_id'),
            config('chatify.pusher.options'),
        );
    }
    /**
     * This method returns the allowed image extensions
     * to attach with the message.
     *
     * @return array
     */
    public function getAllowedImages()
    {
        return config('chatify.attachments.allowed_images');
    }

    /**
     * This method returns the allowed file extensions
     * to attach with the message.
     *
     * @return array
     */
    public function getAllowedFiles()
    {
        return config('chatify.attachments.allowed_files');
    }

    /**
     * Returns an array contains messenger's colors
     *
     * @return array
     */
    public function getMessengerColors()
    {
        return config('chatify.colors');
    }

    /**
     * Trigger an event using Pusher
     *
     * @param string $channel
     * @param string $event
     * @param array $data
     * @return void
     */
    public function push($channel, $event, $data)
    {
        return $this->pusher->trigger($channel, $event, $data);
    }

    /**
     * Authentication for pusher
     *
     * @param User $requestUser
     * @param User $authUser
     * @param string $channelName
     * @param string $socket_id
     * @param array $data
     * @return void
     */
    public function pusherAuth($requestUser, $authUser, $channelName, $socket_id)
    {
        // Auth data
        $authData = json_encode([
            'user_id' => get_class($authUser) . '#' . $authUser->id,
            'user_info' => [
                'name' => $authUser->name
            ]
        ]);
        // check if user authenticated
        if (Auth::check()) {
            if ($requestUser->id == $authUser->id && get_class($requestUser) == get_class($authUser)) {
                return $this->pusher->socket_auth(
                    $channelName,
                    $socket_id,
                    $authData
                );
            }
            // if not authorized
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // if not authenticated
        return response()->json(['message' => 'Not authenticated'], 403);
    }

    /**
     * Fetch message by id and return the message card
     * view as a response.
     *
     * @param int $id
     * @return array
     */
    public function fetchMessage($id, $index = null, $message = null)
    {
        $msg = null;
        $attachment = null;
        $attachment_type = null;
        $attachment_title = null;

        if (!!$message) {
            $msg = $message;
        } else {
            $msg = Message::where('id', $id)->first();
            if (!$msg) {
                return [];
            }
        }

        if (isset($msg->attachment)) {
            $attachmentOBJ = json_decode($msg->attachment);
            $attachment = $attachmentOBJ->new_name;
            $attachment_title = htmlentities(trim($attachmentOBJ->old_name), ENT_QUOTES, 'UTF-8');

            $ext = pathinfo($attachment, PATHINFO_EXTENSION);
            $attachment_type = in_array($ext, $this->getAllowedImages()) ? 'image' : 'file';
        }

        return [
            'index' => $index,
            'id' => $msg->id,
            'from_id' => $msg->from_id,
            'from_type' => $msg->from_type,
            'to_id' => $msg->to_id,
            'to_type' => $msg->to_type,
            'room_id' => $msg->room_id,
            'message' => $msg->body,
            'attachment' => [$attachment, $attachment_title, $attachment_type],
            'time' => $msg->created_at->diffForHumans(),
            'fullTime' => $msg->created_at,
            'viewType' => ($msg->from_id == Auth::user()->id) ? 'sender' : 'default',
            'seen' => $msg->seen,
        ];
    }

    /**
     * Return a message card with the given data.
     *
     * @param array $data
     * @param string $viewType
     * @return string
     */
    public function messageCard($data, $viewType = null)
    {
        if (!$data) {
            return '';
        }
        $data['viewType'] = ($viewType) ? $viewType : $data['viewType'];
        return view('Chatify::layouts.messageCard', $data)->render();
    }

    /**
     * Default fetch messages query between a Sender and Receiver.
     *
     * @param int $user_id
     * @param string $user_type
     * @return Message|\Illuminate\Database\Eloquent\Builder
     */
    public function fetchMessagesQuery($user_id, $user_type)
    {
        return Message::where('from_id', Auth::user()->id)->where('to_id', $user_id)
            ->where('from_type', get_class(Auth::user()))->where('to_type', $user_type)
            ->orWhere('from_id', $user_id)->where('to_id', Auth::user()->id)
            ->where('from_type', $user_type)->where('to_type', get_class(Auth::user()));
    }

    /**
     * create a new message to database
     *
     * @param array $data
     * @return Message
     */
    public function newMessage($data)
    {
        $message = new Message();
        $message->type = $data['type'];
        $message->from_id = $data['from_id'];
        $message->from_type = $data['from_type'];
        $message->to_id = $data['to_id'];
        $message->to_type = $data['to_type'];
        $message->room_id = $data['room_id'];
        $message->body = $data['body'];
        $message->attachment = $data['attachment'];
        $message->save();
        return $message;
    }

    /**
     * Make messages between the sender [Auth user] and
     * the receiver [User id] as seen.
     *
     * @param int $user_id
     * 
     * @return bool
     */
    public function makeSeen($user_id, $user_type)
    {
        Message::Where('from_id', $user_id)
            ->where('from_type', $user_type)
            ->where('to_id', Auth::user()->id)
            ->where('to_type', get_class(Auth::user()))
            ->where('seen', 0)
            ->update(['seen' => 1]);
        return 1;
    }

    /**
     * Get last message for a specific user
     *
     * @param int $user_id
     * @param string $user_type
     * @return Message|Collection|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getLastMessageQuery($user_id, $user_type)
    {
        return $this->fetchMessagesQuery($user_id, $user_type)->latest()->first();
    }

    /**
     * Count Unseen messages
     *
     * @param int $user_id
     * @param string $user_type
     * @return Collection
     */
    public function countUnseenMessages($user_id, $user_type)
    {
        return Message::where('from_id', $user_id)
            ->where('from_type', $user_type)
            ->where('to_id', Auth::user()->id)
            ->where('to_type', get_class(Auth::user()))
            ->where('seen', 0)->count();
    }

    /**
     * Get user list's item data [Contact Itme]
     * (e.g. User data, Last message, Unseen Counter...)
     *
     * @param int $messenger_id
     * @param Collection $user
     * @return string
     */
    public function getContactItem($user)
    {
        // get last message
        $lastMessage = $this->getLastMessageQuery($user->id, get_class($user));

        // Get Unseen messages counter
        $unseenCounter = $this->countUnseenMessages($user->id, get_class($user));

        return view('Chatify::layouts.listItem', [
            'get' => 'users',
            'user' => $this->getUserWithAvatar($user),
            'lastMessage' => $lastMessage,
            'unseenCounter' => $unseenCounter,
        ])->render();
    }

    /**
     * Get user with avatar (formatted).
     *
     * @param Collection $user
     * @return Collection
     */
    public function getUserWithAvatar($user)
    {
        if ($user->avatar == 'avatar.png' && config('chatify.gravatar.enabled')) {
            $imageSize = config('chatify.gravatar.image_size');
            $imageset = config('chatify.gravatar.imageset');
            $user->avatar = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user->email))) . '?s=' . $imageSize . '&d=' . $imageset;
        } else {
            $user->avatar = self::getUserAvatarUrl($user->avatar);
        }
        return $user;
    }

    /**
     * Check if a user in the favorite list
     *
     * @param int $user_id
     * @param string $user_type
     * @return boolean
     */
    public function inFavorite($user_id, $user_type)
    {
        return Favorite::where('user_id', Auth::user()->id)
            ->where('user_type', get_class(Auth::user()))
            ->where('favorite_id', $user_id)
            ->where('favorite_type', $user_type)
            ->count() > 0
            ? true : false;
    }

    /**
     * Make user in favorite list
     *
     * @param int $user_id
     * @param string $user_type
     * @param int $star
     * @return boolean
     */
    public function makeInFavorite($user_id, $user_type, $action)
    {
        if ($action > 0) {
            // Star
            $star = new Favorite();
            $star->user_id = Auth::user()->id;
            $star->user_type = get_class(Auth::user());
            $star->favorite_id = $user_id;
            $star->favorite_type = $user_type;
            $star->save();
            return $star ? true : false;
        } else {
            // UnStar
            $star = Favorite::where('user_id', Auth::user()->id)
                ->where('user_type', get_class(Auth::user()))
                ->where('favorite_type', $user_type)
                ->where('favorite_id', $user_id)->delete();
            return $star ? true : false;
        }
    }

    /**
     * Get shared photos of the conversation
     *
     * @param int $user_id
     * @param string $user_type
     * @return array
     */
    public function getSharedPhotos($user_id, $user_type)
    {
        $images = array(); // Default
        // Get messages
        $msgs = $this->fetchMessagesQuery($user_id, $user_type)
            ->orderBy('created_at', 'DESC');
        if ($msgs->count() > 0) {
            foreach ($msgs->get() as $msg) {
                // If message has attachment
                if ($msg->attachment) {
                    $attachment = json_decode($msg->attachment);
                    // determine the type of the attachment
                    in_array(pathinfo($attachment->new_name, PATHINFO_EXTENSION), $this->getAllowedImages())
                        ? array_push($images, $attachment->new_name) : '';
                }
            }
        }
        return $images;
    }

    /**
     * Delete Conversation
     *
     * @param int $user_id
     * @param string $user_type
     * @return boolean
     */
    public function deleteConversation($user_id, $user_type)
    {
        try {
            foreach ($this->fetchMessagesQuery($user_id, $user_type)->get() as $msg) {
                // delete file attached if exist
                if (isset($msg->attachment)) {
                    $path = config('chatify.attachments.folder') . '/' . json_decode($msg->attachment)->new_name;
                    if (self::storage()->exists($path)) {
                        self::storage()->delete($path);
                    }
                }
                // delete from database
                $msg->delete();
            }
            return 1;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Delete message by ID
     *
     * @param int $id
     * @return boolean
     */
    public function deleteMessage($id)
    {
        try {
            $msg = Message::where('from_id', auth()->id())
            ->where('from_type', get_class(auth()->user()))
            ->where('id', $id)
            ->firstOrFail();
            if (isset($msg->attachment)) {
                // delete file attached if exist
                $path = config('chatify.attachments.folder') . '/' . json_decode($msg->attachment)->new_name;
                if (self::storage()->exists($path)) {
                    self::storage()->delete($path);
                }
                // delete from database
                $msg->delete();
            } else {
                return 0;
            }
            return 1;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Return a storage instance with disk name specified in the config.
     *
     */
    public function storage()
    {
        return Storage::disk(config('chatify.storage_disk_name'));
    }

    /**
     * Get user avatar url.
     *
     * @param string $user_avatar_name
     * @return string
     */
    public function getUserAvatarUrl($user_avatar_name)
    {
        return self::storage()->url(config('chatify.user_avatar.folder') . '/' . $user_avatar_name);
    }

    /**
     * Get attachment's url.
     *
     * @param string $attachment_name
     * @return string
     */
    public function getAttachmentUrl($attachment_name)
    {
        return self::storage()->url(config('chatify.attachments.folder') . '/' . $attachment_name);
    }
}
