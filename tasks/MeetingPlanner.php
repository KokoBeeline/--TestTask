<?php

class Participant
{
    private int $id;
    private string $name;
    private array $busyIntervals = [];

    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addBusyInterval(int $start, int $end): void
    {
        if ($start >= $end) {
            throw new InvalidArgumentException("Начало интервала должно быть раньше конца");
        }
        $this->busyIntervals[] = ['start' => $start, 'end' => $end];
    }

    public function getBusyIntervals(): array
    {
        return $this->busyIntervals;
    }
}

class MeetingPlanner
{
    private array $participants = [];

    public function addParticipant(Participant $participant): void
    {
        $this->participants[] = $participant;
    }

    /**
     * Ищем свободные слоты когда все участники могут встретиться.
     *
     * Собираем занятые интервалы всех участников, сортируем и мержим пересекающиеся.
     * Потом просто смотрим где есть свободные промежутки нужной длины.
     *
     * Альтернатива - разбить весь диапазон на минуты и отмечать занятые,
     * но если диапазон большой (типа неделя) то это много памяти займёт.
     */
    public function findAvailableSlots(int $duration, int $startTime, int $endTime): array
    {
        if ($duration <= 0) {
            throw new InvalidArgumentException("Длительность должна быть положительной");
        }
        if ($startTime >= $endTime) {
            throw new InvalidArgumentException("Некорректный диапазон времени");
        }

        $allBusy = [];
        foreach ($this->participants as $p) {
            foreach ($p->getBusyIntervals() as $interval) {
                $allBusy[] = $interval;
            }
        }

        // если никто не занят - возвращаем весь диапазон
        if (empty($allBusy)) {
            if ($endTime - $startTime >= $duration) {
                return [['start' => $startTime, 'end' => $endTime]];
            }
            return [];
        }

        usort($allBusy, fn($a, $b) => $a['start'] <=> $b['start']);

        // мержим пересекающиеся интервалы
        $merged = [$allBusy[0]];
        for ($i = 1; $i < count($allBusy); $i++) {
            $last = &$merged[count($merged) - 1];
            if ($allBusy[$i]['start'] <= $last['end']) {
                $last['end'] = max($last['end'], $allBusy[$i]['end']);
            } else {
                $merged[] = $allBusy[$i];
            }
        }

        $slots = [];
        $cursor = $startTime;

        foreach ($merged as $busy) {
            // пропускаем интервалы вне нашего диапазона
            if ($busy['end'] <= $startTime) continue;
            if ($busy['start'] >= $endTime) break;

            // нашли свободный промежуток?
            if ($cursor < $busy['start']) {
                $slotEnd = min($busy['start'], $endTime);
                if ($slotEnd - $cursor >= $duration) {
                    $slots[] = ['start' => $cursor, 'end' => $slotEnd];
                }
            }
            $cursor = max($cursor, $busy['end']);
        }

        // не забываем про конец диапазона
        if ($cursor < $endTime && $endTime - $cursor >= $duration) {
            $slots[] = ['start' => $cursor, 'end' => $endTime];
        }

        return $slots;
    }
}
