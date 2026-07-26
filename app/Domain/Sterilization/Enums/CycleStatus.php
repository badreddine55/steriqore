<?php

namespace App\Domain\Sterilization\Enums;

/**
 * draft -> running -> completed -> awaiting_release -> released | rejected
 * (CLAUDE.md). Released/Rejected are terminal and are only ever reached
 * through the release service (S3-ANA-03) — nothing in S3-ANA-02 writes
 * them, but the type is defined in full now since it IS the state machine
 * this ticket delivers.
 */
enum CycleStatus: string
{
    case Draft = 'draft';
    case Running = 'running';
    case Completed = 'completed';
    case AwaitingRelease = 'awaiting_release';
    case Released = 'released';
    case Rejected = 'rejected';
}
