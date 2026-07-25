import type { ApplicationStatus } from './api';
import type { Tone } from '@/components/ui';

export const APPLICATION_STATUS_OPTIONS: { value: ApplicationStatus; label: string; tone: Tone }[] = [
  { value: 'pending', label: 'Pending', tone: 'neutral' },
  { value: 'selected', label: 'Selected', tone: 'success' },
  { value: 'waiting', label: 'Waiting', tone: 'warning' },
  { value: 'rejected', label: 'Rejected', tone: 'danger' },
  { value: 'admitted', label: 'Admitted', tone: 'brand' },
];

/** Statuses an application can be moved to manually (admitted is reached only via convert). */
export const STATUS_TRANSITIONS: ApplicationStatus[] = ['pending', 'selected', 'waiting', 'rejected'];

export function statusMeta(status: ApplicationStatus) {
  return APPLICATION_STATUS_OPTIONS.find((s) => s.value === status) ?? APPLICATION_STATUS_OPTIONS[0];
}

export const GENDER_OPTIONS = [
  { value: 'male', label: 'Male' },
  { value: 'female', label: 'Female' },
  { value: 'other', label: 'Other' },
] as const;

export const RELIGION_OPTIONS = [
  { value: 'islam', label: 'Islam' },
  { value: 'hindu', label: 'Hindu' },
  { value: 'christian', label: 'Christian' },
  { value: 'buddhist', label: 'Buddhist' },
  { value: 'others', label: 'Others' },
] as const;
