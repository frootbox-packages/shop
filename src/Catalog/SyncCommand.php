<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'shop:sync', description: 'Brings the catalog of the shop up to date with the provider')]
final class SyncCommand extends Command
{
    public function __construct(private readonly SyncSteps $steps)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('full', null, InputOption::VALUE_NONE, 'The whole catalog instead of the changes since the last sync');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->steps->run((bool) $input->getOption('full'), \PHP_INT_MAX);

        if (null === $result) {
            $io->error('Another sync is running, e.g. in the queue. Try again in a minute.');

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            '%s: %d products changed, %d deleted, %d categories changed, %d images copied, %d removed.',
            $result->full ? 'Whole catalog' : 'Changes',
            $result->products,
            $result->deleted,
            $result->categories,
            $result->images,
            $result->removedImages,
        ));

        return Command::SUCCESS;
    }
}
