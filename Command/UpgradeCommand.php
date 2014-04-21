<?php

namespace Modera\UpgradeBundle\Command;

use Modera\UpgradeBundle\Json\JsonFile;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2014 Modera Foundation
 */
class UpgradeCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('modera:upgrade')
            ->setDescription('Command update dependencies in "composer.json" and running needed commands')
            ->setDefinition([
                new InputOption('dependencies', null, InputOption::VALUE_NONE, 'Update dependencies in "composer.json"'),
                new InputOption('run-commands', null, InputOption::VALUE_NONE, 'Run commands'),
                new InputOption('versions-path', null, InputOption::VALUE_OPTIONAL, 'versions.json path', dirname(__DIR__) . '/versions.json'),
            ])
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getHelperSet()->get('dialog');
        $dependencies = $input->getOption('dependencies');
        $runCommands = $input->getOption('run-commands');
        $versionsPath = $input->getOption('versions-path');

        $basePath = dirname($this->getContainer()->get('kernel')->getRootdir());
        $composerFile = new JsonFile($basePath . '/composer.json');
        $versionsFile = new JsonFile($versionsPath);

        $composerData = $composerFile->read();
        $versionsData = $versionsFile->read();

        if ($dependencies) {

            $newVersion = null;
            $versions = array_keys($versionsData);
            $version = isset($composerData['version']) ? $composerData['version'] : null;

            if ($version && $version == $versions[count($versions) - 1]) {
                $output->writeln('<info>You have the latest version</info>');
                return;
            }

            // backup composer.json
            file_put_contents(
                $basePath . '/composer' . ($version ? '.v' . $version : '') . '.backup.json',
                file_get_contents($basePath . '/composer.json')
            );

            $oldDependencies = $newDependencies = array();
            if (!$version) {
                $newVersion = array_keys($versionsData)[0];
                $newDependencies = array_values($versionsData)[0]['dependencies'];
            } else {
                foreach(array_keys($versions) as $k) {
                    if ($versions[$k] == $version) {
                        $newVersion = $versions[$k + 1];
                        $oldDependencies = $versionsData[$version]['dependencies'];
                        $newDependencies = $versionsData[$newVersion]['dependencies'];
                        break;
                    }
                }
            }
            $diff = $this->diffDependencies($oldDependencies, $newDependencies);

            $dependencies = $composerData['require'];
            foreach ($diff['added'] as $name => $ver) {
                if (!isset($dependencies[$name])) {
                    $dependencies[$name] = $ver;
                } else {
                    if ($ver !== $dependencies[$name]) {
                        $msg = "\n" . '<question>Dependency "' . $name . ':' . $dependencies[$name] . '" already exists. ' .
                            'Would you like to change it to "' . $name . ':' . $ver . '"? (Y/n)</question> ';
                        if ($dialog->askConfirmation($output, $msg)) {
                            $dependencies[$name] = $ver;
                        }
                    }
                }
            }
            foreach ($diff['changed'] as $name => $ver) {
                if (!isset($dependencies[$name]) || $oldDependencies[$name] == $dependencies[$name] || $ver == $dependencies[$name]) {
                    $dependencies[$name] = $ver;
                } else {
                    $msg = "\n" . '<question>Dependency "' . $name . ':' . $dependencies[$name] . '" already changed. ' .
                        'Would you like to change it to "' . $name . ':' . $ver . '"? (Y/n)</question> ';
                    if ($dialog->askConfirmation($output, $msg)) {
                        $dependencies[$name] = $ver;
                    }
                }
            }
            foreach ($diff['removed'] as $name => $ver) {
                if (isset($dependencies[$name])) {
                    $msg = "\n" . '<question>Dependency "' . $name . '" has been removed. ' .
                        'Would you like to remove it? (Y/n)</question> ';
                    if ($dialog->askConfirmation($output, $msg)) {
                        unset($dependencies[$name]);
                    }
                }
            }
            foreach ($diff['same'] as $name => $ver) {
                if (!isset($dependencies[$name])) {
                    $dependencies[$name] = $ver;
                } else if ($ver !== $dependencies[$name]) {
                    $msg = "\n" . '<question>Dependency "' . $name . ':' . $dependencies[$name] . '" has been manually changed. ' .
                        'Would you like to restore it to "' . $name . ':' .$ver . '"? (Y/n)</question> ';
                    if ($dialog->askConfirmation($output, $msg)) {
                        $dependencies[$name] = $ver;
                    }
                }
            }
            $composerData['require'] = $dependencies;
            $composerData['version'] = $newVersion;

            $repositories = isset($composerData['repositories']) ? $composerData['repositories'] : array();
            if (isset($versionsData[$newVersion]['add-repositories'])) {
                foreach ($versionsData[$newVersion]['add-repositories'] as $repo) {
                    if (false === array_search($repo, $repositories)) {
                        $repositories[] = $repo;
                    }
                }
            }
            if (isset($versionsData[$newVersion]['rm-repositories'])) {
                foreach ($versionsData[$newVersion]['rm-repositories'] as $repo) {
                    if (false !== ($key = array_search($repo, $repositories))) {
                        unset($repositories[$key]);
                    }
                }
                $repositories = array_values($repositories);
            }
            $composerData['repositories'] = $repositories;

            $composerFile->write($composerData);

            $output->writeln("<info>composer.json 'requires' section has been updated to version $newVersion</info>");

            if (count($versionsData[$newVersion]['commands'])) {
                $output->writeln('After composer update run:');
                $output->writeln('<info>php composer.phar ' . $this->getName() . ' --run-commands</info>');
            }

        } else if ($runCommands) {

            if (isset($composerData['version']) && isset($versionsData[$composerData['version']])) {
                $commands = $versionsData[$composerData['version']]['commands'];
                foreach ($commands as $command) {
                    $this->getApplication()->run(new StringInput($command), $output);
                }

                if (count($commands) == 0) {
                    $output->writeln('<comment>No commands need to be run! Aborting ...</comment>');
                }
            }

        } else {
            $msg = [
                'If you want to update dependencies then please use <info>--dependencies</info> flag for the command, ',
                'if you need to have commands executed when a version is upgraded then use <info>--run-commands</info> flag instead.'
            ];

            $output->writeln(implode($msg, ''));
            $output->writeln('<info>php composer.phar ' . $this->getName() . ' --dependencies</info>');
        }
    }

    /**
     * @param array $arr1
     * @param array $arr2
     * @return array
     */
    private function diffDependencies(array $arr1, array $arr2)
    {
        $diff1   = array_diff_assoc($arr1, $arr2);
        $diff2   = array_diff_assoc($arr2, $arr1);
        $added   = array_diff_key($diff2, $diff1);
        $changed = array_diff_key($diff2, $added);
        $removed = array_diff_key($diff1, $diff2);
        $same    = array_diff_key($arr1, $diff1);

        return array(
            'added'   => $added,
            'changed' => $changed,
            'removed' => $removed,
            'same'    => $same,
        );
    }
}
