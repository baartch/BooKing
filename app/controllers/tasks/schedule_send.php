<?php

function runScheduleSendTask(PDO $pdo): void
{
    runScheduledEmailTasks($pdo);
}
