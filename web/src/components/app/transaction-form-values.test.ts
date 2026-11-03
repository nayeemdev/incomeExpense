import type { Account, TransactionGroup } from '@/lib/api/types'
import { emptySplit, groupToFormValues, toInput } from './transaction-form-values'

const currency = (id: number, code: string) => ({ id, code, name: code, decimal_places: 2 })

const account = (id: number, code: string): Account =>
  ({ id, type: 'asset', name: `Account ${id}`, currency: currency(id, code), active: true }) as Account

describe('toInput', () => {
  it('sends new counterparties and categories by name', () => {
    const input = toInput(
      {
        type: 'withdrawal',
        date: '2026-11-02',
        title: '',
        splits: [
          emptySplit({
            description: ' Coffee ',
            amount: '3.5',
            source: { id: 1 },
            destination: { name: 'Corner Cafe' },
            category: { name: 'Eating out' },
          }),
        ],
      },
      [account(1, 'USD')],
    )

    expect(input.title).toBeNull()
    expect(input.transactions[0]).toMatchObject({
      description: 'Coffee',
      source_id: 1,
      destination_name: 'Corner Cafe',
      category_id: null,
      category_name: 'Eating out',
      foreign_amount: null,
    })
  })

  it('adds the destination currency for a cross-currency transfer', () => {
    const input = toInput(
      {
        type: 'transfer',
        date: '2026-11-02',
        title: '',
        splits: [emptySplit({ description: 'Move', amount: '100', source: { id: 1 }, destination: { id: 2 }, foreign_amount: '92' })],
      },
      [account(1, 'USD'), account(2, 'EUR')],
    )

    expect(input.transactions[0]).toMatchObject({ foreign_amount: '92', foreign_currency_id: 2 })
  })

  it('keeps the title only for split transactions', () => {
    const split = emptySplit({ description: 'a', amount: '1', source: { id: 1 }, destination: { name: 'x' } })
    const input = toInput({ type: 'withdrawal', date: '2026-11-02', title: 'Mall', splits: [split, split] }, [])

    expect(input.title).toBe('Mall')
  })
})

describe('payee defaults', () => {
  it('uses the description as payee when none is chosen', () => {
    const input = toInput(
      { type: 'withdrawal', date: '2026-11-03', title: '', splits: [emptySplit({ description: ' Rickshaw ', amount: '60', source: { id: 1 } })] },
      [account(1, 'BDT')],
    )

    expect(input.transactions[0]).toMatchObject({ destination_id: null, destination_name: 'Rickshaw' })
  })
})

describe('groupToFormValues', () => {
  it('turns expense and revenue accounts back into names', () => {
    const group = {
      id: 9,
      title: null,
      transactions: [
        {
          type: 'withdrawal',
          date: '2026-11-02',
          description: 'Groceries',
          amount: '12.00',
          source: { id: 1, name: 'Checking', type: 'asset' },
          destination: { id: 5, name: 'Market', type: 'expense' },
          category: { id: 3, name: 'Food' },
          tags: [{ id: 1, name: 'weekly' }],
          notes: null,
          foreign_amount: null,
        },
      ],
    } as unknown as TransactionGroup

    expect(groupToFormValues(group).splits[0]).toMatchObject({
      source: { id: 1 },
      destination: { name: 'Market' },
      category: { id: 3, name: 'Food' },
      tags: ['weekly'],
    })
  })
})
