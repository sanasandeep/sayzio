import { describe, it, expect } from 'vitest';
import {
  defaultProfile,
  profileFromWorkspace,
  sessionPartitionForProfile,
  profileSyncEntityKey,
  DEFAULT_PROFILE_ID,
} from '../src/shared/profile-store';

describe('defaultProfile', () => {
  it('returns a personal profile with id="default"', () => {
    const p = defaultProfile();
    expect(p.id).toBe('default');
    expect(p.isPersonal).toBe(true);
    expect(p.workspaceId).toBeNull();
    expect(p.name).toBe('Personal');
  });
});

describe('profileFromWorkspace', () => {
  it('maps a workspace API object to a profile', () => {
    const ws = { id: 42, name: 'Acme Corp', is_personal: false };
    const p = profileFromWorkspace(ws);
    expect(p.id).toBe('42');
    expect(p.workspaceId).toBe('42');
    expect(p.name).toBe('Acme Corp');
    expect(p.isPersonal).toBe(false);
  });

  it('marks a personal workspace correctly', () => {
    const ws = { id: 1, name: 'My space', is_personal: true };
    const p = profileFromWorkspace(ws);
    expect(p.isPersonal).toBe(true);
  });

  it('coerces numeric id to string', () => {
    const ws = { id: 99, name: 'Team' };
    const p = profileFromWorkspace(ws);
    expect(typeof p.id).toBe('string');
    expect(p.id).toBe('99');
  });
});

describe('sessionPartitionForProfile', () => {
  it('produces a persist: partition string', () => {
    const partition = sessionPartitionForProfile('default');
    expect(partition).toMatch(/^persist:/);
  });

  it('includes the profile id in the partition name', () => {
    expect(sessionPartitionForProfile('42')).toContain('42');
  });

  it('generates distinct partitions for different profiles', () => {
    const a = sessionPartitionForProfile('default');
    const b = sessionPartitionForProfile('42');
    const c = sessionPartitionForProfile('43');
    expect(a).not.toBe(b);
    expect(b).not.toBe(c);
  });
});

describe('profileSyncEntityKey', () => {
  it('returns entity:profileId format', () => {
    expect(profileSyncEntityKey('bookmarks', DEFAULT_PROFILE_ID)).toBe(`bookmarks:${DEFAULT_PROFILE_ID}`);
  });

  it('different profiles produce different keys', () => {
    const k1 = profileSyncEntityKey('history', 'default');
    const k2 = profileSyncEntityKey('history', '5');
    expect(k1).not.toBe(k2);
  });
});

describe('DEFAULT_PROFILE_ID', () => {
  it('is the string "default"', () => {
    expect(DEFAULT_PROFILE_ID).toBe('default');
  });
});
