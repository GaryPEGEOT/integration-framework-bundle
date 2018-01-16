<?php

namespace Smartbox\Integration\FrameworkBundle\Components\DB;

use Smartbox\CoreBundle\Type\SerializableArray;
use Smartbox\Integration\FrameworkBundle\Components\DB\Dbal\ConfigurableDbalProtocol;
use Smartbox\Integration\FrameworkBundle\Components\DB\NoSQL\NoSQLConfigurableProtocol;
use Smartbox\Integration\FrameworkBundle\Configurability\IsConfigurableService;
use Smartbox\Integration\FrameworkBundle\Core\Consumers\ConfigurableConsumerInterface;
use Smartbox\Integration\FrameworkBundle\Core\Consumers\Exceptions\NoResultsException;
use Smartbox\Integration\FrameworkBundle\Core\Consumers\IsStopableConsumer;
use Smartbox\Integration\FrameworkBundle\Core\Endpoints\EndpointInterface;
use Smartbox\Integration\FrameworkBundle\Core\Messages\Context;
use Smartbox\Integration\FrameworkBundle\Core\Messages\MessageInterface;
use Smartbox\Integration\FrameworkBundle\DependencyInjection\Traits\UsesSmartesbHelper;
use Smartbox\Integration\FrameworkBundle\Service;

class DBConfigurableConsumer extends Service implements ConfigurableConsumerInterface
{
    use IsStopableConsumer;
    use UsesSmartesbHelper;
    use IsConfigurableService;

    const SLEEP_TIME = 100; // Duration of the pause made in the consume loop, when nothing to do (slow mode), in milliseconds.
    const INACTIVITY_TRIGGER = 10; // Inactivity duration before switching to slow mode, in seconds.

    /** @var ConfigurableStepsProviderInterface */
    protected $configurableStepsProvider;

    /**
     * @return ConfigurableStepsProviderInterface
     */
    public function getConfigurableStepsProvider()
    {
        return $this->configurableStepsProvider;
    }

    /**
     * @param ConfigurableStepsProviderInterface $configurableStepsProvider
     */
    public function setConfigurableStepsProvider($configurableStepsProvider)
    {
        $this->configurableStepsProvider = $configurableStepsProvider;
    }

    /**
     * Reads a message from the NoSQL database executing the configured steps.
     *
     * @param EndpointInterface $endpoint
     *
     * @return \Smartbox\Integration\FrameworkBundle\Core\Messages\Message
     */
    protected function readMessage(EndpointInterface $endpoint)
    {
        $options = $endpoint->getOptions();
        $method = $options[NoSQLConfigurableProtocol::OPTION_METHOD];
        $config = $this->methodsConfiguration[$method];
        $steps = $config[ConfigurableConsumerInterface::CONFIG_QUERY_STEPS];

        $context = $this->getConfHelper()->createContext($options);

        try {
            $this->configurableStepsProvider->executeSteps($steps, $options, $context);

            $result = $this->getConfHelper()->resolve(
                $config[ConfigurableConsumerInterface::CONFIG_QUERY_RESULT],
                $context
            );
        } catch (NoResultsException $exception) {
            $result = null;
            if ($options[ConfigurableDbalProtocol::OPTION_STOP_ON_NO_RESULTS]) {
                $this->stop();
            }
        }

        if ($result == null) {
            return null;
        } elseif (is_array($result)) {
            $result = new SerializableArray($result);
        }

        $context = new Context([
            Context::FLOWS_VERSION => $this->getFlowsVersion(),
            Context::TRANSACTION_ID => uniqid('', true),
            Context::ORIGINAL_FROM => $endpoint->getURI(),
        ]);

        return $this->smartesbHelper->getMessageFactory()->createMessage($result, [], $context);
    }

    /**
     * Executes the necessary actions after the message has been consumed.
     *
     * @param EndpointInterface $endpoint
     * @param MessageInterface  $message
     */
    protected function onConsume(EndpointInterface $endpoint, MessageInterface $message)
    {
        $options = $endpoint->getOptions();
        $method = $options[NoSQLConfigurableProtocol::OPTION_METHOD];
        $config = $this->methodsConfiguration[$method];
        $steps = $config[ConfigurableConsumerInterface::CONFIG_ON_CONSUME];

        $context = $this->getConfHelper()->createContext($options, $message);

        $this->configurableStepsProvider->executeSteps($steps, $options, $context);
    }

    public function consume(EndpointInterface $endpoint)
    {
        $iFeelAsleep = false;
        $wakeup = microtime();

        while (!$this->shouldStop()) {
            // Receive
            $message = $this->readMessage($endpoint);

            // Process
            if ($message) {
                $iFeelAsleep = false;
                $wakeup = microtime();
                --$this->expirationCount;

                $endpoint->handle($message);

                $this->onConsume($endpoint, $message);
            }
            if ($iFeelAsleep) {
                usleep(self::SLEEP_TIME * 1000); // 100 ms
            }

            if ((microtime() - $wakeup) > self::INACTIVITY_TRIGGER) { // I did nothing since the last x seconds, so I enter the slow mode...
                $iFeelAsleep = true;
            }
        }
    }
}
