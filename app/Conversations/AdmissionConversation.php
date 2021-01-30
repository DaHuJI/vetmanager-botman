<?php

declare(strict_types=1);

namespace App\Conversations;

use App\Http\Helpers\Rest\Admission;
use App\Vetmanager\MainMenu;
use BotMan\BotMan\Messages\Conversations\Conversation;
use App\Http\Helpers\Rest\Users;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use GuzzleHttp\Client;
use Otis22\VetmanagerToken\Token\Concrete;
use App\Vetmanager\UserData\ClinicToken;

final class AdmissionConversation extends Conversation
{

    public function sayTop10()
    {
        try {
            $token = new Concrete(
                (
                    new ClinicToken(
                        $this->getBot()
                    )
                )->asString()
            );
            $baseUri = $this->getBot()->userStorage()->get('clinicUrl');
            $client = new Client(
                [
                    'base_uri' => $baseUri,
                    'headers' => ['X-USER-TOKEN' => $token->asString(), 'X-APP-NAME' => config('app.name')]
                ]
            );
            $currentUserLogin = $this->getBot()->userStorage()->get('userLogin');
            $users = new Users($client);
            $currentUserId = $users->getUserIdByLogin($currentUserLogin);
            $admission = new Admission($client);
            $last10Admissions = array_slice($admission->getByUserId($currentUserId)['data']['admission'], 0, 10, true);
            foreach ($last10Admissions as $concrete) {
                $message = $concrete['admission_date'] . ' - ' . $concrete['client']['last_name'] . " ";
                $message .= $concrete['client']['first_name'] . " " . $concrete['pet']['alias'] . " ";
                $message .= $concrete['pet']['pet_type_data']['title'] . " " . $concrete['pet']['breed_data']['title'];
                $this->say($message);
            }
        } catch (\Throwable $exception) {
            $this->sayError("Ошибка: " . $exception->getMessage());
        }
    }

    public function sayError(string $message) {
        $this->say($message);
        $this->say("Попробуйте повторить команду после авторизации.");
        $this->say(
            (
                new MainMenu(
                    [Question::class, 'create'],
                    [Button::class, 'create']
                )
            )->asQuestion()
        );
    }

    public function run()
    {
        $this->sayTop10();
    }
}
