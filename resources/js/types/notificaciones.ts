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

export type AudienceCourse = {
    /** Id del taller que representa el curso. */
    id: number;
    /** Título sin el prefijo "Sesión N:" cuando el curso está dividido. */
    name: string;
    day: string | null;
    days: string[];
    is_divided: boolean;
    total_sessions: number;
    workshop_ids: number[];
};

export type AudienceUser = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
};
