import { createServer } from 'node:net';

const label = process.env.BE6A1_HARNESS_CHILD_LABEL ?? 'unnamed';
const mode = process.env.BE6A1_HARNESS_CHILD_MODE ?? 'cooperative';
const port = Number(process.env.BE6A1_HARNESS_CHILD_PORT);

if (!Number.isInteger(port) || port < 1 || port > 65_535) {
    throw new Error(`Invalid harness child port: ${process.env.BE6A1_HARNESS_CHILD_PORT}`);
}

const server = createServer((socket) => socket.end());
const keepAlive = setInterval(() => {}, 1_000);

function shutdown() {
    if (mode === 'stubborn') return;
    clearInterval(keepAlive);
    server.close(() => process.exit(0));
}

process.on('message', (message) => {
    if (message?.type === 'shutdown') shutdown();
});

server.listen(port, '127.0.0.1', () => {
    if (process.send) process.send({ type: 'ready', label, mode, port, pid: process.pid });
});
