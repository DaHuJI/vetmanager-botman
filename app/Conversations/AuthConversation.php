<?php
/**
 * https://dev.to/devkiran/building-a-salon-booking-chatbot-with-laravel-and-botman-1250
 */
declare(strict_types=1);

namespace App\Conversations;

use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use App\Vetmanager\UserData\ClinicUrl;
use BotMan\BotMan\Storages\Storage;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use function Otis22\VetmanagerUrl\url;
use function Otis22\VetmanagerToken\token;
use function Otis22\VetmanagerToken\credentials;
use function config;

final class AuthConversation extends Conversation
{
    /**
     * @var string
     */
    private $appName;
    /**
     * @var string
     */
    protected $clinicUrl;
    /**
     * @var string
     */
    protected $userLogin;
    /**
     * @var string
     */
    protected $token;

    /**
     * AuthConversation constructor.
     * @param string $appName
     */
    public function __construct(string $appName)
    {
        $this->appName = $appName;
    }

    /**
     * @return Conversation
     */
    public function askDomain(): Conversation
    {
        return $this->ask("Введите доменное имя или адрес программы. Пример: myclinic или https://myclinic.vetmanager.ru", function (Answer $answer) {
            try {
                if (empty(trim($answer->getText()))) {
                    throw new \Exception("Can't be empty text");
                }
                $this->getBot()->userStorage()
                    ->save(
                        ['clinicDomain' => $answer->getText()]
                    );
                $this->clinicUrl = (
                    new ClinicUrl(
                        $this->getBot(),
                        function (string $domain) : string {
                            return url($domain)->asString();
                        }
                    )
                )->asString();
                $this->askLogin();
            } catch (\Throwable $exception) {
                $this->say("Попробуйте еще раз. Ошибка: " . $exception->getMessage());
                $this->askDomain();
            }
        });
    }

    /**
     * @return Conversation
     */
    public function askLogin(): Conversation
    {
        return $this->ask("Введите login вашего пользователя в Ветменеджер", function (Answer $answer) {
            $this->userLogin = $answer->getText();
            $this->getBot()->userStorage()
                ->save(
                    ['userLogin' => $this->userLogin]
                );
            $this->askPassword();
        });
    }

    public function askPassword(): Conversation
    {
        return $this->ask("Введите пароль вашего пользователя в Ветменеджер", function (Answer $answer) {
            $password = $answer->getText();
            $credentials = credentials(
                $this->userLogin,
                $password,
                $this->appName
            );
            try {
                $token = token($credentials, $this->clinicUrl)->asString();
                $this->getBot()->userStorage()
                    ->save(
                        ['clinicUserToken' => $token]
                    );
                $this->say('Успех! Введите start для вывода списка команд');
            } catch (\Throwable $exception) {
                $this->say("Попробуйте еще раз. Ошибка: " . $exception->getMessage());
                $this->askDomain();
            }
        });
    }

    public function run()
    {
        if (empty($this->userData()->get('clinicUrl'))) {
            $this->say("Привет, Босс, ответьте на 3 вопроса");
            $this->askDomain();
            return;
        }
        if (empty($this->userData()->get('clinicUserLogin'))) {
            $this->say("Привет, Босс, ответьте на 2 вопроса");
            $this->askLogin();
            return;
        }
    }
    private function userData(): Storage
    {
        return $this->getBot()
            ->userStorage(
                $this->getBot()->getUser()
            );
    }

    /**
     * @param IncomingMessage $message
     * @return bool
     */
    public function stopsConversation(IncomingMessage $message): bool
    {
        if ($message->getText() == 'stop') {
            return true;
        }

        return false;
    }
}
