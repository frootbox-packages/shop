<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use Frootbox\Core\Action\Runner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'shop:sync', description: 'Brings the catalog of the shop up to date with the provider')]
final class SyncCommand extends Command
{
    public function __construct(private readonly Runner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('full', null, InputOption::VALUE_NONE, 'The whole catalog instead of the changes since the last sync');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->runner->run(new SyncCatalog((bool) $input->getOption('full')));
        new SymfonyStyle($input, $output)->success(\sprintf(
            '%s: %d products changed, %d deleted, %d categories changed, %d images copied.',
            $result->full ? 'Whole catalog' : 'Changes',
            $result->products,
            $result->deleted,
            $result->categories,
            $result->images,
        ));

        return Command::SUCCESS;
    }
}
