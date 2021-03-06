<?php

namespace Shopsys\FrameworkBundle\Component\Cron;

use DateTimeInterface;
use Shopsys\FrameworkBundle\Component\Cron\Config\CronConfig;
use Shopsys\FrameworkBundle\Component\Cron\Config\CronModuleConfig;
use Shopsys\FrameworkBundle\Model\Mail\Mailer;
use Swift_TransportException;
use Symfony\Bridge\Monolog\Logger;

class CronFacade
{
    protected const TIMEOUT_SECONDS = 4 * 60;

    /**
     * @var \Symfony\Bridge\Monolog\Logger
     */
    protected $logger;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Cron\Config\CronConfig
     */
    protected $cronConfig;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Cron\CronModuleFacade
     */
    protected $cronModuleFacade;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Mail\Mailer
     */
    protected $mailer;

    /**
     * @param \Symfony\Bridge\Monolog\Logger $logger
     * @param \Shopsys\FrameworkBundle\Component\Cron\Config\CronConfig $cronConfig
     * @param \Shopsys\FrameworkBundle\Component\Cron\CronModuleFacade $cronModuleFacade
     * @param \Shopsys\FrameworkBundle\Model\Mail\Mailer $mailer
     */
    public function __construct(
        Logger $logger,
        CronConfig $cronConfig,
        CronModuleFacade $cronModuleFacade,
        Mailer $mailer
    ) {
        $this->logger = $logger;
        $this->cronConfig = $cronConfig;
        $this->cronModuleFacade = $cronModuleFacade;
        $this->mailer = $mailer;
    }

    /**
     * @param \DateTimeInterface $roundedTime
     */
    public function scheduleModulesByTime(DateTimeInterface $roundedTime)
    {
        $cronModuleConfigsToSchedule = $this->cronConfig->getCronModuleConfigsByTime($roundedTime);
        $this->cronModuleFacade->scheduleModules($cronModuleConfigsToSchedule);
    }

    /**
     * @param string $instanceName
     */
    public function runScheduledModulesForInstance(string $instanceName): void
    {
        $cronModuleExecutor = new CronModuleExecutor(static::TIMEOUT_SECONDS);

        $cronModuleConfigs = $this->cronConfig->getCronModuleConfigsForInstance($instanceName);

        $scheduledCronModuleConfigs = $this->cronModuleFacade->getOnlyScheduledCronModuleConfigs($cronModuleConfigs);
        $this->runModulesForInstance($cronModuleExecutor, $scheduledCronModuleConfigs, $instanceName);
    }

    /**
     * @param \Shopsys\FrameworkBundle\Component\Cron\CronModuleExecutor $cronModuleExecutor
     * @param array $cronModuleConfigs
     * @param string $instanceName
     */
    protected function runModulesForInstance(CronModuleExecutor $cronModuleExecutor, array $cronModuleConfigs, string $instanceName): void
    {
        $this->logger->addInfo(sprintf('====== Start of cron instance %s ======', $instanceName));

        foreach ($cronModuleConfigs as $cronModuleConfig) {
            $this->runModule($cronModuleExecutor, $cronModuleConfig);
            if ($cronModuleExecutor->canRun() === false) {
                break;
            }
        }

        $this->logger->addInfo(sprintf('======= End of cron instance %s =======', $instanceName));
    }

    /**
     * @param string $serviceId
     */
    public function runModuleByServiceId($serviceId)
    {
        $cronModuleConfig = $this->cronConfig->getCronModuleConfigByServiceId($serviceId);

        $cronModuleExecutor = new CronModuleExecutor(static::TIMEOUT_SECONDS);
        $this->runModule($cronModuleExecutor, $cronModuleConfig);
    }

    /**
     * @param \Shopsys\FrameworkBundle\Component\Cron\CronModuleExecutor $cronModuleExecutor
     * @param \Shopsys\FrameworkBundle\Component\Cron\Config\CronModuleConfig $cronModuleConfig
     */
    protected function runModule(CronModuleExecutor $cronModuleExecutor, CronModuleConfig $cronModuleConfig)
    {
        $this->logger->addInfo('Start of ' . $cronModuleConfig->getServiceId());
        $cronModuleService = $cronModuleConfig->getService();
        $cronModuleService->setLogger($this->logger);
        $status = $cronModuleExecutor->runModule(
            $cronModuleService,
            $this->cronModuleFacade->isModuleSuspended($cronModuleConfig)
        );

        try {
            $this->mailer->flushSpoolQueue();
        } catch (Swift_TransportException $exception) {
            $this->logger->addError('An exception occurred while flushing email queue. Message: "{message}"', ['exception' => $exception, 'message' => $exception->getMessage()]);
        }

        if ($status === CronModuleExecutor::RUN_STATUS_OK) {
            $this->cronModuleFacade->unscheduleModule($cronModuleConfig);
            $this->logger->addInfo('End of ' . $cronModuleConfig->getServiceId());
        } elseif ($status === CronModuleExecutor::RUN_STATUS_SUSPENDED) {
            $this->cronModuleFacade->suspendModule($cronModuleConfig);
            $this->logger->addInfo('Suspend ' . $cronModuleConfig->getServiceId());
        } elseif ($status === CronModuleExecutor::RUN_STATUS_TIMEOUT) {
            $this->logger->info('Cron reached timeout.');
        }
    }

    /**
     * @return \Shopsys\FrameworkBundle\Component\Cron\Config\CronModuleConfig[]
     */
    public function getAll()
    {
        return $this->cronConfig->getAllCronModuleConfigs();
    }

    /**
     * @param string $instanceName
     * @return \Shopsys\FrameworkBundle\Component\Cron\Config\CronModuleConfig[]
     */
    public function getAllForInstance(string $instanceName): array
    {
        return $this->cronConfig->getCronModuleConfigsForInstance($instanceName);
    }

    /**
     * @return string[]
     */
    public function getInstanceNames(): array
    {
        return array_unique(array_map(function (CronModuleConfig $config) {
            return $config->getInstanceName();
        }, $this->getAll()));
    }
}
