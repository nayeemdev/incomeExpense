import { describeSchedule } from './recurrence'

describe('describeSchedule', () => {
  it('describes each repetition type in a sentence', () => {
    const describe = (type: 'daily' | 'weekly' | 'monthly' | 'ndom' | 'yearly', moment: string, skip = 0) =>
      describeSchedule({ repetition: { type, moment, skip, weekend: 'keep' } }, 'en-US')

    expect(describe('daily', '')).toBe('Every day')
    expect(describe('daily', '', 2)).toBe('Every 3 days')
    expect(describe('weekly', '5')).toBe('Every week on Friday')
    expect(describe('monthly', '1', 1)).toBe('Every 2 months on day 1')
    expect(describe('ndom', '2,2')).toBe('Every month on the second Tuesday')
    expect(describe('yearly', '03-15')).toBe('Every year on March 15')
  })
})
