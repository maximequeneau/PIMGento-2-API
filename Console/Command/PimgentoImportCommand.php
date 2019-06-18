<?php

namespace Pimgento\Api\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Data\Collection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Pimgento\Api\Api\ImportRepositoryInterface;
use Pimgento\Api\Job\Import;
use \Symfony\Component\Console\Command\Command;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Input\InputOption;

/**
 * Class PimgentoImportCommand
 *
 * @category  Class
 * @package   Pimgento\Api\Console\Command
 * @author    Agence Dn'D <contact@dnd.fr>
 * @copyright 2018 Agence Dn'D
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://www.pimgento.com/
 */
class PimgentoImportCommand extends Command
{
    /**
     * This constant contains a string
     *
     * @var string IMPORT_CODE
     */
    const IMPORT_CODE = 'code';
    /**
     * This variable contains a State
     *
     * @var State $appState
     */
    protected $appState;
    /**
     * This variable contains a ImportRepositoryInterface
     *
     * @var ImportRepositoryInterface $importRepository
     */
    private $importRepository;

    /**
     * PimgentoImportCommand constructor.
     *
     * @param ImportRepositoryInterface\Proxy $importRepository
     * @param State                           $appState
     * @param null                            $name
     */
    public function __construct(
        ImportRepositoryInterface\Proxy $importRepository,
        State $appState,
        $name = null
    ) {
        parent::__construct($name);

        $this->appState         = $appState;
        $this->importRepository = $importRepository;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('pimgento:import')
            ->setDescription('Import PIM data to Magento')
            ->addOption(
                self::IMPORT_CODE,
                null,
                InputOption::VALUE_REQUIRED
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException $exception) {
            /** @var string $message */
            $message = __('Area code already set')->getText();
            $output->writeln($message);
        }

        /** @var string $code */
        $code = $input->getOption(self::IMPORT_CODE);
        if (!$code) {
            $this->usage($output);
        } else {
            $this->import($code, $output);
        }
    }

    /**
     * Run import
     *
     * @param string          $code
     * @param OutputInterface $output
     *
     * @return bool
     */
    protected function import(string $code, OutputInterface $output): bool
    {
        /** @var Import $import */
        $import = $this->importRepository->getByCode($code);
        if (!$import) {
            /** @var Phrase $message */
            $message = __('Import code not found');
            $this->displayError($message, $output);

            return false;
        }

        try {
            $import->setStep(0);

            while ($import->canExecute()) {
                /** @var string $comment */
                $comment = $import->getComment();
                $this->displayInfo($comment, $output);

                $import->execute();

                /** @var string $message */
                $message = $import->getMessage();
                if (!$import->getStatus()) {
                    $this->displayError($message, $output);
                } else {
                    $this->displayComment($message, $output);
                }

                if ($import->isDone()) {
                    break;
                }
            }
        } catch (\Exception $exception) {
            /** @var string $message */
            $message = $exception->getMessage();
            $this->displayError($message, $output);
        }

        return true;
    }

    /**
     * Print command usage
     *
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function usage(OutputInterface $output): void
    {
        /** @var Collection $imports */
        $imports = $this->importRepository->getList();

        // Options
        $this->displayComment(__('Options:'), $output);
        $this->displayInfo(__('--code'), $output);
        $output->writeln('');

        // Codes
        $this->displayComment(__('Available codes:'), $output);
        /** @var Import $import */
        foreach ($imports as $import) {
            $this->displayInfo($import->getCode(), $output);
        }
        $output->writeln('');

        // Example
        /** @var Import $import */
        $import = $imports->getFirstItem();
        /** @var string $code */
        $code = $import->getCode();
        if ($code) {
            $this->displayComment(__('Example:'), $output);
            $this->displayInfo(__('pimgento:import --code=%1', $code), $output);
        }
    }

    /**
     * Display info in console
     *
     * @param string          $message
     * @param OutputInterface $output
     *
     * @return void
     */
    public function displayInfo(string $message, OutputInterface $output): void
    {
        if (!empty($message)) {
            /** @var string $coloredMessage */
            $coloredMessage = '<info>'.$message.'</info>';
            $output->writeln($coloredMessage);
        }
    }

    /**
     * Display comment in console
     *
     * @param string          $message
     * @param OutputInterface $output
     *
     * @return void
     */
    public function displayComment(string $message, OutputInterface $output): void
    {
        if (!empty($message)) {
            /** @var string $coloredMessage */
            $coloredMessage = '<comment>'.$message.'</comment>';
            $output->writeln($coloredMessage);
        }
    }

    /**
     * Display error in console
     *
     * @param string          $message
     * @param OutputInterface $output
     *
     * @return void
     */
    public function displayError(string $message, OutputInterface $output): void
    {
        if (!empty($message)) {
            /** @var string $coloredMessage */
            $coloredMessage = '<error>'.$message.'</error>';
            $output->writeln($coloredMessage);
        }
    }
}
