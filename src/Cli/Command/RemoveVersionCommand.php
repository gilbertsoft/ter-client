<?php
namespace Helhum\TerClient\Cli\Command;

use Helhum\TerClient\Connection;
use Helhum\TerClient\Deleter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Remove version command
 */
class RemoveVersionCommand extends Command
{
    /**
     * Configure
     */
    protected function configure()
    {
        $this->setName('remove-version')
            ->addArgument(
                'extensionKey',
                InputArgument::REQUIRED,
                'Extension key'
            )
            ->addArgument(
                'version',
                InputArgument::REQUIRED,
                'Version to be removed from TER'
            )
            ->addOption(
                'username',
                '-u',
                InputOption::VALUE_OPTIONAL,
                'Username of account which is owner of the extension'
            )
            ->addOption(
                'password',
                '-p',
                InputOption::VALUE_OPTIONAL,
                'Password of account which is owner of the extension'
            )
            ->addOption(
                'wsd-url',
                null,
                InputOption::VALUE_OPTIONAL,
                'Alternative WSD URL / SOAP Endpoint',
                Connection::WSDL_URL
            );
        $this->setDescription('Removes version of given extension in TER. This command is only available to TER admins!');
    }

    /**
     * Execute
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws \SoapFault
     * @return null|int null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $extensionKey = $input->getArgument('extensionKey');
        $version = $input->getArgument('version');
        $username = $input->getOption('username');
        $password = $input->getOption('password');
        $wsdUrl = $input->getOption('wsd-url');

        while (empty($username)) {
            $username = $this->ask($input, $output, '<comment>Please specify a username:</comment> ');
        }
        while (empty($password)) {
            $password = $this->ask($input, $output, '<comment>Please specify a password:</comment> ', true);
        }

        $deleter = new Deleter(Connection::create($wsdUrl));
        $result = $deleter->deleteExtensionVersion($extensionKey, $version, $username, $password);

        if (isset($result[Connection::SOAP_RETURN_VERSION])) {
            $output->writeln('<info>Succesfully removed TER extension version: ' . $result[Connection::SOAP_RETURN_VERSION] . '</info>');
        }

        if (isset($result[Connection::SOAP_RETURN_MESSAGES])) {
            foreach ($result[Connection::SOAP_RETURN_MESSAGES] as $index => $message) {
                $output->writeln('<comment>Message #' . ($index + 1) . ':</comment> ' . $message);
            }
        }

        return 0;
    }

    /**
     * Asks a question to the user
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string|array $question The question to ask. If an array each array item is turned into one line of a multi-line question
     * @param bool $hidden
     * @return string The user answer
     */
    private function ask(InputInterface $input, OutputInterface $output, $question, $hidden = false)
    {
        $questionHelper = new QuestionHelper();
        $helperSet = new HelperSet(array(new FormatterHelper()));
        $questionHelper->setHelperSet($helperSet);

        $question = new Question($question);
        $question
            ->setHidden($hidden)
            ->setHiddenFallback(true);

        return $questionHelper->ask($input, $output, $question);
    }
}
