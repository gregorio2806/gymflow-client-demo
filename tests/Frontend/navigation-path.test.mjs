import assert from 'node:assert/strict';
import test from 'node:test';
import { isCurrentOrChildPath } from '../../resources/js/lib/navigation-path.ts';

await test('navigation matches route boundaries rather than shared name prefixes', () => {
    assert.equal(isCurrentOrChildPath('/membership-plans', '/members'), false);
    assert.equal(
        isCurrentOrChildPath('/membership-plans/1/edit', '/members'),
        false,
    );
    assert.equal(
        isCurrentOrChildPath('/membership-plans/1/edit', '/membership-plans'),
        true,
    );
    assert.equal(isCurrentOrChildPath('/members', '/members'), true);
    assert.equal(isCurrentOrChildPath('/members/66/edit', '/members'), true);
    assert.equal(
        isCurrentOrChildPath('/settings/profile-other', '/settings/profile'),
        false,
    );
    assert.equal(isCurrentOrChildPath('/members', '/'), false);
    assert.equal(isCurrentOrChildPath('/', '/'), true);
});
