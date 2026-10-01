export type AudienceSegmentType =
    | 'all_users'
    | 'role'
    | 'speakers_by_kind'
    | 'workshop_enrollment'
    | 'workshop_instructors'
    | 'individual';

export type AudienceSegment =
    | { type: 'all_users' }
    | { type: 'role'; role_ids: number[] }
    | { type: 'speakers_by_kind'; kinds: string[] }
    | {
          type: 'workshop_enrollment' | 'workshop_instructors';
          workshop_ids: number[];
          all_workshops: boolean;
      }
    | { type: 'individual'; user_ids: number[] };

export type AudienceWorkshop = {
    id: number;
    name: string;
    day: string | null;
    start_time: string | null;
    parent_workshop_id: number | null;
};

export type AudienceUser = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
};
