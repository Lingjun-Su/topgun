<?php

namespace Tests\Unit;

use App\Models\Contract\MasterContract;
use App\Services\Contract\MasterContractStateMachine;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class MasterContractStateMachineTest extends TestCase
{
    private function makeContractWithStatus(int $status): MasterContract
    {
        $contract = Mockery::mock(MasterContract::class)->makePartial();
        $contract->status = $status;

        return $contract;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_draft_can_transition_to_pending(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_DRAFT);
        $sm = new MasterContractStateMachine($contract);

        $this->assertTrue($sm->canTransitionTo(MasterContract::STATUS_PENDING));
        $this->assertEquals(MasterContract::STATUS_PENDING, $sm->submit());
    }

    public function test_draft_can_transition_to_void(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_DRAFT);
        $sm = new MasterContractStateMachine($contract);

        $this->assertTrue($sm->canTransitionTo(MasterContract::STATUS_VOID));
        $this->assertEquals(MasterContract::STATUS_VOID, $sm->void());
    }

    public function test_draft_cannot_transition_to_active(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_DRAFT);
        $sm = new MasterContractStateMachine($contract);

        $this->assertFalse($sm->canTransitionTo(MasterContract::STATUS_ACTIVE));
    }

    public function test_pending_can_approve_to_active(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_PENDING);
        $sm = new MasterContractStateMachine($contract);

        $this->assertTrue($sm->canTransitionTo(MasterContract::STATUS_ACTIVE));
        $this->assertEquals(MasterContract::STATUS_ACTIVE, $sm->approve());
    }

    public function test_pending_can_reject(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_PENDING);
        $sm = new MasterContractStateMachine($contract);

        $this->assertTrue($sm->canTransitionTo(MasterContract::STATUS_REJECTED));
        $this->assertEquals(MasterContract::STATUS_REJECTED, $sm->reject());
    }

    public function test_pending_can_withdraw_to_draft(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_PENDING);
        $sm = new MasterContractStateMachine($contract);

        $this->assertTrue($sm->canTransitionTo(MasterContract::STATUS_DRAFT));
        $this->assertEquals(MasterContract::STATUS_DRAFT, $sm->withdraw());
    }

    public function test_rejected_can_resubmit_to_pending(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_REJECTED);
        $sm = new MasterContractStateMachine($contract);

        $this->assertTrue($sm->canTransitionTo(MasterContract::STATUS_PENDING));
        $this->assertEquals(MasterContract::STATUS_PENDING, $sm->submit());
    }

    public function test_rejected_can_be_voided(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_REJECTED);
        $sm = new MasterContractStateMachine($contract);

        $this->assertTrue($sm->canTransitionTo(MasterContract::STATUS_VOID));
        $this->assertEquals(MasterContract::STATUS_VOID, $sm->void());
    }

    public function test_active_can_be_terminated(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_ACTIVE);
        $sm = new MasterContractStateMachine($contract);

        $this->assertTrue($sm->canTransitionTo(MasterContract::STATUS_TERMINATED));
        $this->assertEquals(MasterContract::STATUS_TERMINATED, $sm->terminate());
    }

    public function test_active_cannot_go_back_to_draft(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_ACTIVE);
        $sm = new MasterContractStateMachine($contract);

        $this->assertFalse($sm->canTransitionTo(MasterContract::STATUS_DRAFT));
    }

    public function test_terminated_is_final_state(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_TERMINATED);
        $sm = new MasterContractStateMachine($contract);

        $this->assertFalse($sm->canTransitionTo(MasterContract::STATUS_DRAFT));
        $this->assertFalse($sm->canTransitionTo(MasterContract::STATUS_ACTIVE));
        $this->assertFalse($sm->canTransitionTo(MasterContract::STATUS_PENDING));
    }

    public function test_void_is_final_state(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_VOID);
        $sm = new MasterContractStateMachine($contract);

        $this->assertFalse($sm->canTransitionTo(MasterContract::STATUS_DRAFT));
        $this->assertFalse($sm->canTransitionTo(MasterContract::STATUS_PENDING));
    }

    public function test_transition_to_invalid_throws_exception(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_DRAFT);
        $sm = new MasterContractStateMachine($contract);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('不可从状态 %d 转换到 %d', MasterContract::STATUS_DRAFT, MasterContract::STATUS_ACTIVE));

        $sm->transitionTo(MasterContract::STATUS_ACTIVE);
    }

    public function test_draft_allowed_actions(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_DRAFT);
        $sm = new MasterContractStateMachine($contract);

        $this->assertTrue($sm->isActionAllowedByState('edit'));
        $this->assertTrue($sm->isActionAllowedByState('submit'));
        $this->assertTrue($sm->isActionAllowedByState('void'));
        $this->assertFalse($sm->isActionAllowedByState('approve'));
        $this->assertFalse($sm->isActionAllowedByState('reject'));
    }

    public function test_pending_allowed_actions(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_PENDING);
        $sm = new MasterContractStateMachine($contract);

        $this->assertTrue($sm->isActionAllowedByState('withdraw'));
        $this->assertTrue($sm->isActionAllowedByState('approve'));
        $this->assertTrue($sm->isActionAllowedByState('reject'));
        $this->assertFalse($sm->isActionAllowedByState('edit'));
        $this->assertFalse($sm->isActionAllowedByState('submit'));
    }

    public function test_active_allowed_actions(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_ACTIVE);
        $sm = new MasterContractStateMachine($contract);

        $this->assertTrue($sm->isActionAllowedByState('add_clause'));
        $this->assertTrue($sm->isActionAllowedByState('terminate'));
        $this->assertFalse($sm->isActionAllowedByState('edit'));
        $this->assertFalse($sm->isActionAllowedByState('submit'));
    }

    public function test_add_clause_does_not_change_status(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_ACTIVE);
        $sm = new MasterContractStateMachine($contract);

        $this->assertEquals(MasterContract::STATUS_ACTIVE, $sm->addClause());
    }

    public function test_expired_can_be_terminated(): void
    {
        $contract = $this->makeContractWithStatus(MasterContract::STATUS_EXPIRED);
        $sm = new MasterContractStateMachine($contract);

        $this->assertTrue($sm->canTransitionTo(MasterContract::STATUS_TERMINATED));
    }
}
