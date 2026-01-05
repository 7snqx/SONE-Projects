/**
 * Logic Gate Simulator
 * Core Logic & UI Handling
 */

// --- Configuration & State ---

const CONFIG = {
    gridSize: 20,
    snapThreshold: 10,
    colors: {
        default: '#64748b',
        active: '#10b981',
        hover: '#4338ca',
        error: '#ef4444'
    }
};

const STATE = {
    gates: [], 
    connections: [], 
    nextId: 1,
    isDragging: false,
    draggedGateId: null,
    dragOffset: { x: 0, y: 0 },
    
    // Viewport State
    view: {
        x: 0,
        y: 0,
        zoom: 1
    },
    isPanning: false,
    panStart: { x: 0, y: 0 },
    
    // Interaction State
    connectionMode: null, // 'drag' or 'click'
    tempConnection: null, // { gateId, portIndex, type, x, y }
    dragStartPos: { x: 0, y: 0 },
    selectedGateId: null, // For deletion

    // Oscilloscope State
    oscilloscope: {
        history: [], // Array of { timestamp, values: { probeId: value } }
        maxHistory: 200,
        paused: false
    },
    
    // Animation State
    animation: {
        active: false,
        packets: [], // { pathId, t, speed }
        lastSpawn: 0
    },

    // History (Undo/Redo)
    history: [],
    historyIndex: -1,
    maxHistory: 50
};

// --- Gate Definitions ---

const GATE_DEFS = {
    'INPUT': {
        name: 'INPUT',
        inputs: 0,
        outputs: 1,
        color: 'text-green-600',
        render: (gate) => `<div class="input-toggle ${gate.state.value ? 'on' : ''}" data-action="toggle">${gate.state.value ? '1' : '0'}</div>`,
        logic: (inputs, state) => ({ out0: state.value })
    },
    'OUTPUT': {
        name: 'OUTPUT',
        inputs: 1,
        outputs: 0,
        color: 'text-red-600',
        render: (gate) => `<div class="output-display ${gate.state.value ? 'on' : ''}">${gate.state.value ? '1' : '0'}</div>`,
        logic: (inputs) => {
            const val = inputs[0] || 0;
            return { value: val }; 
        }
    },
    'AND': {
        name: 'AND',
        inputs: 2,
        outputs: 1,
        svgPath: `<path d="M 10 5 L 10 35 L 25 35 A 15 15 0 0 0 25 5 L 10 5 Z" fill="white" stroke="currentColor" stroke-width="2"/><line x1="0" y1="10" x2="10" y2="10" stroke="currentColor" stroke-width="2"/><line x1="0" y1="30" x2="10" y2="30" stroke="currentColor" stroke-width="2"/><line x1="40" y1="20" x2="50" y2="20" stroke="currentColor" stroke-width="2"/>`,
        logic: (inputs) => ({ out0: (inputs[0] && inputs[1]) ? 1 : 0 })
    },
    'OR': {
        name: 'OR',
        inputs: 2,
        outputs: 1,
        svgPath: `<path d="M 10 5 Q 20 20 10 35 Q 40 35 50 20 Q 40 5 10 5 Z" fill="white" stroke="currentColor" stroke-width="2"/><line x1="0" y1="10" x2="10" y2="10" stroke="currentColor" stroke-width="2"/><line x1="0" y1="30" x2="10" y2="30" stroke="currentColor" stroke-width="2"/><line x1="50" y1="20" x2="60" y2="20" stroke="currentColor" stroke-width="2"/>`,
        logic: (inputs) => ({ out0: (inputs[0] || inputs[1]) ? 1 : 0 })
    },
    'NOT': {
        name: 'NOT',
        inputs: 1,
        outputs: 1,
        svgPath: `<path d="M 10 5 L 10 35 L 40 20 Z" fill="white" stroke="currentColor" stroke-width="2"/><circle cx="43" cy="20" r="3" fill="white" stroke="currentColor" stroke-width="2"/><line x1="0" y1="20" x2="10" y2="20" stroke="currentColor" stroke-width="2"/><line x1="46" y1="20" x2="56" y2="20" stroke="currentColor" stroke-width="2"/>`,
        logic: (inputs) => ({ out0: inputs[0] ? 0 : 1 })
    },
    'NAND': {
        name: 'NAND',
        inputs: 2,
        outputs: 1,
        svgPath: `<path d="M 10 5 L 10 35 L 25 35 A 15 15 0 0 0 25 5 L 10 5 Z" fill="white" stroke="currentColor" stroke-width="2"/><circle cx="43" cy="20" r="3" fill="white" stroke="currentColor" stroke-width="2"/><line x1="0" y1="10" x2="10" y2="10" stroke="currentColor" stroke-width="2"/><line x1="0" y1="30" x2="10" y2="30" stroke="currentColor" stroke-width="2"/><line x1="46" y1="20" x2="56" y2="20" stroke="currentColor" stroke-width="2"/>`,
        logic: (inputs) => ({ out0: !(inputs[0] && inputs[1]) ? 1 : 0 })
    },
    'NOR': {
        name: 'NOR',
        inputs: 2,
        outputs: 1,
        svgPath: `<path d="M 10 5 Q 20 20 10 35 Q 40 35 50 20 Q 40 5 10 5 Z" fill="white" stroke="currentColor" stroke-width="2"/><circle cx="53" cy="20" r="3" fill="white" stroke="currentColor" stroke-width="2"/><line x1="0" y1="10" x2="10" y2="10" stroke="currentColor" stroke-width="2"/><line x1="0" y1="30" x2="10" y2="30" stroke="currentColor" stroke-width="2"/><line x1="56" y1="20" x2="60" y2="20" stroke="currentColor" stroke-width="2"/>`,
        logic: (inputs) => ({ out0: !(inputs[0] || inputs[1]) ? 1 : 0 })
    },
    'XOR': {
        name: 'XOR',
        inputs: 2,
        outputs: 1,
        svgPath: `<path d="M 10 5 Q 20 20 10 35 Q 40 35 50 20 Q 40 5 10 5 Z" fill="white" stroke="currentColor" stroke-width="2"/><path d="M 2 5 Q 12 20 2 35" fill="none" stroke="currentColor" stroke-width="2"/><line x1="0" y1="10" x2="5" y2="10" stroke="currentColor" stroke-width="2"/><line x1="0" y1="30" x2="5" y2="30" stroke="currentColor" stroke-width="2"/><line x1="50" y1="20" x2="60" y2="20" stroke="currentColor" stroke-width="2"/>`,
        logic: (inputs) => ({ out0: (inputs[0] !== inputs[1]) ? 1 : 0 })
    },
    'XNOR': {
        name: 'XNOR',
        inputs: 2,
        outputs: 1,
        svgPath: `<path d="M 10 5 Q 20 20 10 35 Q 40 35 50 20 Q 40 5 10 5 Z" fill="white" stroke="currentColor" stroke-width="2"/><path d="M 2 5 Q 12 20 2 35" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="53" cy="20" r="3" fill="white" stroke="currentColor" stroke-width="2"/><line x1="0" y1="10" x2="5" y2="10" stroke="currentColor" stroke-width="2"/><line x1="0" y1="30" x2="5" y2="30" stroke="currentColor" stroke-width="2"/><line x1="56" y1="20" x2="60" y2="20" stroke="currentColor" stroke-width="2"/>`,
        logic: (inputs) => ({ out0: (inputs[0] === inputs[1]) ? 1 : 0 })
    },
    'HALF_ADDER': {
        name: 'HALF ADDER',
        inputs: 2,
        outputs: 2, // Sum, Carry
        labels: { in: ['A', 'B'], out: ['S', 'C'] },
        customPorts: {
            in: [
                { left: 15, top: -5 }, // A (Top-Left)
                { left: 35, top: -5 }  // B (Top-Right)
            ],
            out: [
                { left: 25, top: 35 }, // Sum (Bottom)
                { left: -5, top: 15 }  // Carry (Left)
            ]
        },
        svgPath: `
            <rect x="2" y="2" width="56" height="36" rx="2" fill="white" stroke="currentColor" stroke-width="2"/>
            <text x="30" y="24" text-anchor="middle" font-family="sans-serif" font-size="10px" font-weight="bold" fill="#1e293b" stroke="none">HA</text>
            <text x="15" y="12" text-anchor="middle" font-size="8px" fill="#64748b">A</text>
            <text x="45" y="12" text-anchor="middle" font-size="8px" fill="#64748b">B</text>
            <text x="30" y="34" text-anchor="middle" font-size="8px" fill="#64748b">S</text>
            <text x="8" y="24" text-anchor="middle" font-size="8px" fill="#64748b">C</text>
        `,
        logic: (inputs) => {
            const a = inputs[0] || 0;
            const b = inputs[1] || 0;
            return { out0: a ^ b, out1: a & b };
        }
    },
    'FULL_ADDER': {
        name: 'FULL ADDER',
        inputs: 3,
        outputs: 2, // Sum, Cout
        labels: { in: ['A', 'B', 'Cin'], out: ['S', 'Cout'] },
        customPorts: {
            in: [
                { left: 15, top: -5 }, // A (Top-Left)
                { left: 35, top: -5 }, // B (Top-Right)
                { left: 55, top: 15 }  // Cin (Right)
            ],
            out: [
                { left: 25, top: 35 }, // Sum (Bottom)
                { left: -5, top: 15 }  // Cout (Left)
            ]
        },
        svgPath: `
            <rect x="2" y="2" width="56" height="36" rx="2" fill="white" stroke="currentColor" stroke-width="2"/>
            <text x="30" y="24" text-anchor="middle" font-family="sans-serif" font-size="10px" font-weight="bold" fill="#1e293b" stroke="none">FA</text>
            <text x="15" y="12" text-anchor="middle" font-size="8px" fill="#64748b">A</text>
            <text x="45" y="12" text-anchor="middle" font-size="8px" fill="#64748b">B</text>
            <text x="52" y="24" text-anchor="middle" font-size="8px" fill="#64748b">Cin</text>
            <text x="30" y="34" text-anchor="middle" font-size="8px" fill="#64748b">S</text>
            <text x="10" y="24" text-anchor="middle" font-size="8px" fill="#64748b">Cout</text>
        `,
        logic: (inputs) => {
            const a = inputs[0] || 0;
            const b = inputs[1] || 0;
            const cin = inputs[2] || 0;
            const sum = a ^ b ^ cin;
            const cout = (a & b) | (cin & (a ^ b));
            return { out0: sum, out1: cout };
        }
    },
    'CLOCK': {
        name: 'CLOCK',
        inputs: 0,
        outputs: 1,
        color: 'text-blue-600',
        render: (gate) => `<div class="clock-display ${gate.state.value ? 'on' : ''} flex items-center justify-center w-full h-full border-2 border-blue-500 rounded bg-white text-blue-600 font-bold"><span class="material-symbols-rounded text-[20px]">schedule</span></div>`,
        logic: (inputs, state) => ({ out0: state.value })
    },
    'D_FF': {
        name: 'D Flip-Flop',
        inputs: 2, // D, Clk
        outputs: 2, // Q, !Q
        width: 60,
        height: 40,
        labels: { in: ['D', '>'], out: ['Q', 'Q̅'] },
        svgPath: `
            <rect x="2" y="2" width="56" height="36" rx="2" fill="white" stroke="currentColor" stroke-width="2"/>
            <text x="30" y="12" text-anchor="middle" font-family="sans-serif" font-size="8px" font-weight="bold" fill="#1e293b" stroke="none">D-FF</text>
            <text x="10" y="15" text-anchor="middle" font-size="8px" fill="#64748b">D</text>
            <path d="M 2 25 L 8 28 L 2 31" fill="none" stroke="#64748b" stroke-width="1"/>
            <text x="50" y="15" text-anchor="middle" font-size="8px" fill="#64748b">Q</text>
            <text x="50" y="30" text-anchor="middle" font-size="8px" fill="#64748b">Q̅</text>
        `,
        logic: (inputs, state) => {
            const d = inputs[0] || 0;
            const clk = inputs[1] || 0;
            
            // Rising edge detection
            if (clk === 1 && state.lastClk === 0) {
                state.q = d;
            }
            state.lastClk = clk;
            
            const q = state.q || 0;
            return { out0: q, out1: q ? 0 : 1 };
        }
    },
    'JK_FF': {
        name: 'JK Flip-Flop',
        inputs: 3, // J, K, Clk
        outputs: 2, // Q, !Q
        width: 60,
        height: 40,
        labels: { in: ['J', 'K', '>'], out: ['Q', 'Q̅'] },
        svgPath: `
            <rect x="2" y="2" width="56" height="36" rx="2" fill="white" stroke="currentColor" stroke-width="2"/>
            <text x="30" y="12" text-anchor="middle" font-family="sans-serif" font-size="8px" font-weight="bold" fill="#1e293b" stroke="none">JK-FF</text>
            <text x="10" y="12" text-anchor="middle" font-size="8px" fill="#64748b">J</text>
            <text x="10" y="32" text-anchor="middle" font-size="8px" fill="#64748b">K</text>
            <path d="M 2 19 L 8 22 L 2 25" fill="none" stroke="#64748b" stroke-width="1"/>
            <text x="50" y="15" text-anchor="middle" font-size="8px" fill="#64748b">Q</text>
            <text x="50" y="30" text-anchor="middle" font-size="8px" fill="#64748b">Q̅</text>
        `,
        logic: (inputs, state) => {
            const j = inputs[0] || 0;
            const k = inputs[1] || 0;
            const clk = inputs[2] || 0;
            
            // Rising edge detection
            if (clk === 1 && state.lastClk === 0) {
                const q = state.q || 0;
                if (j === 0 && k === 0) { /* No change */ }
                else if (j === 0 && k === 1) { state.q = 0; } // Reset
                else if (j === 1 && k === 0) { state.q = 1; } // Set
                else if (j === 1 && k === 1) { state.q = q ? 0 : 1; } // Toggle
            }
            state.lastClk = clk;
            
            const q = state.q || 0;
            return { out0: q, out1: q ? 0 : 1 };
        }
    },
    'MUX': {
        name: 'Multiplexer 4:1',
        inputs: 6, // I0-I3, S0, S1
        outputs: 1,
        width: 100,
        height: 60,
        labels: { in: ['I0', 'I1', 'I2', 'I3', 'S0', 'S1'], out: ['Y'] },
        customPorts: {
            in: [
                { left: 20, top: -5 },  // I0
                { left: 40, top: -5 },  // I1
                { left: 60, top: -5 },  // I2
                { left: 80, top: -5 },  // I3
                { left: -5, top: 20 },  // S0
                { left: -5, top: 40 }   // S1
            ],
            out: [
                { left: 95, top: 30 }   // Y
            ]
        },
        svgPath: `
            <path d="M 10 5 L 90 15 L 90 45 L 10 55 Z" fill="white" stroke="currentColor" stroke-width="2"/>
            <text x="50" y="35" text-anchor="middle" font-family="sans-serif" font-size="12px" font-weight="bold" fill="#1e293b" stroke="none">MUX</text>
            <text x="20" y="18" text-anchor="middle" font-size="8px" fill="#64748b">0</text>
            <text x="80" y="18" text-anchor="middle" font-size="8px" fill="#64748b">3</text>
        `,
        logic: (inputs) => {
            const s0 = inputs[4] || 0;
            const s1 = inputs[5] || 0;
            const sel = s0 + (s1 * 2);
            return { out0: inputs[sel] || 0 };
        }
    },
    'DEMUX': {
        name: 'Demultiplexer 1:4',
        inputs: 3, // In, S0, S1
        outputs: 4, // Y0-Y3
        width: 100,
        height: 60,
        labels: { in: ['In', 'S0', 'S1'], out: ['Y0', 'Y1', 'Y2', 'Y3'] },
        customPorts: {
            in: [
                { left: 5, top: 30 },   // In
                { left: 40, top: 55 },  // S0
                { left: 60, top: 55 }   // S1
            ],
            out: [
                { left: 95, top: 15 },   // Y0
                { left: 95, top: 25 },  // Y1
                { left: 95, top: 35 },  // Y2
                { left: 95, top: 45 }   // Y3
            ]
        },
        svgPath: `
            <path d="M 10 15 L 90 5 L 90 55 L 10 45 Z" fill="white" stroke="currentColor" stroke-width="2"/>
            <text x="50" y="35" text-anchor="middle" font-family="sans-serif" font-size="12px" font-weight="bold" fill="#1e293b" stroke="none">DEMUX</text>
        `,
        logic: (inputs) => {
            const val = inputs[0] || 0;
            const s0 = inputs[1] || 0;
            const s1 = inputs[2] || 0;
            const sel = s0 + (s1 * 2);
            
            return {
                out0: sel === 0 ? val : 0,
                out1: sel === 1 ? val : 0,
                out2: sel === 2 ? val : 0,
                out3: sel === 3 ? val : 0
            };
        }
    },
    'ALU4': {
        name: 'ALU 4-bit',
        inputs: 10, // A0-3, B0-3, Op0-1
        outputs: 5, // R0-3, Cout
        width: 100,
        height: 80,
        labels: { 
            in: ['A0', 'A1', 'A2', 'A3', 'B0', 'B1', 'B2', 'B3', 'Op0', 'Op1'], 
            out: ['R0', 'R1', 'R2', 'R3', 'Cout'] 
        },
        customPorts: {
            in: [
                { left: 35, top: -5 }, { left: 45, top: -5 }, { left: 55, top: -5 }, { left: 65, top: -5 }, // A
                { left: 35, top: 85 }, { left: 45, top: 85 }, { left: 55, top: 85 }, { left: 65, top: 85 }, // B
                { left: -5, top: 30 }, { left: -5, top: 50 } // Op
            ],
            out: [
                { left: 105, top: 25 }, { left: 105, top: 35 }, { left: 105, top: 45 }, { left: 105, top: 55 }, // R
                { left: 95, top: 70 } // Cout
            ]
        },
        svgPath: `
            <path d="M 10 5 L 90 25 L 90 55 L 10 75 L 10 50 L 30 40 L 10 30 Z" fill="white" stroke="currentColor" stroke-width="2"/>
            <text x="55" y="45" text-anchor="middle" font-family="sans-serif" font-size="14px" font-weight="bold" fill="#1e293b" stroke="none">ALU</text>
            <text x="35" y="15" font-size="10px" fill="#64748b">A</text>
            <text x="35" y="70" font-size="10px" fill="#64748b">B</text>
        `,
        logic: (inputs) => {
            // Parse inputs
            const a = (inputs[0] || 0) + ((inputs[1] || 0) << 1) + ((inputs[2] || 0) << 2) + ((inputs[3] || 0) << 3);
            const b = (inputs[4] || 0) + ((inputs[5] || 0) << 1) + ((inputs[6] || 0) << 2) + ((inputs[7] || 0) << 3);
            const op = (inputs[8] || 0) + ((inputs[9] || 0) << 1);
            
            let res = 0;
            let cout = 0;
            
            switch(op) {
                case 0: // ADD
                    res = a + b;
                    cout = (res > 15) ? 1 : 0;
                    res = res & 0xF;
                    break;
                case 1: // SUB
                    res = a - b;
                    if (res < 0) res += 16; // 2's complement ish
                    res = res & 0xF;
                    break;
                case 2: // AND
                    res = a & b;
                    break;
                case 3: // OR
                    res = a | b;
                    break;
            }
            
            return {
                out0: res & 1,
                out1: (res >> 1) & 1,
                out2: (res >> 2) & 1,
                out3: (res >> 3) & 1,
                out4: cout
            };
        }
    },
    'BUFFER': {
        name: 'BUFFER',
        inputs: 1,
        outputs: 1,
        width: 40,
        height: 40,
        labels: { in: ['In'], out: ['Out'] },
        render: (gate) => {
            const val = gate.state.value || 0;
            return `
                <div class="w-[40px] h-[40px] bg-white dark:bg-slate-800 border-2 ${val ? 'border-green-500 shadow-[0_0_10px_rgba(34,197,94,0.5)]' : 'border-slate-400 dark:border-slate-500'} rounded flex items-center justify-center transition-all duration-200">
                    <span class="material-symbols-rounded text-[20px] ${val ? 'text-green-600' : 'text-slate-400'}">forward</span>
                </div>
            `;
        },
        logic: (inputs, state) => {
            const val = inputs[0] || 0;
            state.value = val;
            return { out0: val };
        }
    },
    'RAM': {
        name: 'RAM 8x4',
        width: 100,
        height: 90,
        inputs: 9, // A0-2, D0-3, WE, CLK
        outputs: 4, // Q0-3
        labels: { 
            in: ['A0', 'A1', 'A2', 'D0', 'D1', 'D2', 'D3', 'WE', '>'], 
            out: ['Q0', 'Q1', 'Q2', 'Q3'] 
        },
        customPorts: {
            in: [
                { left: -5, top: 25 }, { left: -5, top: 45 }, { left: -5, top: 65 }, // Addr
                { left: 35, top: -5 }, { left: 45, top: -5 }, { left: 55, top: -5 }, { left: 65, top: -5 }, // Data
                { left: 30, top: 85 }, { left: 70, top: 85 } // WE, CLK (Adjusted to be on bottom edge of 90px height)
            ],
            out: [
                { left: 95, top: 15 }, { left: 95, top: 35 }, { left: 95, top: 55 }, { left: 95, top: 75 } // Q (Adjusted to right edge of 100px width)
            ]
        },
        render: (gate) => {
            // Initialize memory if not exists
            if (!gate.state.memory) {
                gate.state.memory = new Array(8).fill(0);
            }
            
            // Get current address value to display
            const addr = (gate.inputs[0] || 0) + ((gate.inputs[1] || 0) << 1) + ((gate.inputs[2] || 0) << 2);
            const val = gate.state.memory[addr];
            
            return `
                <div class="w-[100px] h-[90px] bg-white dark:bg-slate-800 border-2 border-slate-700 dark:border-slate-500 rounded flex flex-col items-center justify-center relative">
                    <div class="text-[12px] font-bold text-slate-800 dark:text-slate-200">RAM 8x4</div>
                    <div class="flex gap-2 mt-2">
                        <div class="text-[10px] text-slate-500">A:${addr}</div>
                        <div class="text-[10px] text-blue-600 dark:text-blue-400 font-mono">D:${val.toString(16).toUpperCase()}</div>
                    </div>
                    <div class="absolute bottom-1 left-8 text-[8px] text-slate-400">WE</div>
                    <div class="absolute bottom-1 right-8 text-[8px] text-slate-400">CLK</div>
                </div>
            `;
        },
        logic: (inputs, state) => {
            if (!state.memory) state.memory = new Array(8).fill(0);
            
            const addr = (inputs[0] || 0) + ((inputs[1] || 0) << 1) + ((inputs[2] || 0) << 2);
            const dataIn = (inputs[3] || 0) + ((inputs[4] || 0) << 1) + ((inputs[5] || 0) << 2) + ((inputs[6] || 0) << 3);
            const we = inputs[7] || 0;
            const clk = inputs[8] || 0;
            
            // Write on rising edge
            if (clk === 1 && state.lastClk === 0 && we === 1) {
                state.memory[addr] = dataIn;
            }
            state.lastClk = clk;
            
            // Read always
            const val = state.memory[addr];
            
            return {
                out0: val & 1,
                out1: (val >> 1) & 1,
                out2: (val >> 2) & 1,
                out3: (val >> 3) & 1
            };
        }
    },
    'ROM': {
        name: 'ROM 8x4',
        width: 100,
        height: 90,
        inputs: 3, // A0-2
        outputs: 4, // D0-3
        labels: { in: ['A0', 'A1', 'A2'], out: ['D0', 'D1', 'D2', 'D3'] },
        customPorts: {
            in: [
                { left: -5, top: 25 }, { left: -5, top: 45 }, { left: -5, top: 65 }
            ],
            out: [
                { left: 95, top: 15 }, { left: 95, top: 35 }, { left: 95, top: 55 }, { left: 95, top: 75 } // Adjusted to right edge of 100px width
            ]
        },
        render: (gate) => {
            if (!gate.state.memory) {
                // Default program: Counter pattern
                gate.state.memory = [0, 1, 2, 3, 4, 5, 6, 7];
            }
            const addr = (gate.inputs[0] || 0) + ((gate.inputs[1] || 0) << 1) + ((gate.inputs[2] || 0) << 2);
            const val = gate.state.memory[addr];
            return `
                <div class="w-[100px] h-[90px] bg-slate-100 dark:bg-slate-800 border-2 border-slate-700 dark:border-slate-500 rounded flex flex-col items-center justify-center relative cursor-pointer" title="Double click to edit">
                    <div class="text-[12px] font-bold text-slate-800 dark:text-slate-200">ROM</div>
                    <div class="flex gap-2 mt-2">
                        <div class="text-[10px] text-slate-500">A:${addr}</div>
                        <div class="text-[10px] text-purple-600 dark:text-purple-400 font-mono">D:${val.toString(16).toUpperCase()}</div>
                    </div>
                </div>
            `;
        },
        logic: (inputs, state) => {
            if (!state.memory) state.memory = [0, 1, 2, 3, 4, 5, 6, 7];
            const addr = (inputs[0] || 0) + ((inputs[1] || 0) << 1) + ((inputs[2] || 0) << 2);
            const val = state.memory[addr];
            return {
                out0: val & 1,
                out1: (val >> 1) & 1,
                out2: (val >> 2) & 1,
                out3: (val >> 3) & 1
            };
        }
    },
    'COUNTER_4BIT': {
        name: 'Counter 4-bit',
        inputs: 2, // Clk, Rst
        outputs: 4, // Q0-3
        width: 80,
        height: 60,
        labels: { in: ['>', 'R'], out: ['Q0', 'Q1', 'Q2', 'Q3'] },
        customPorts: {
            in: [
                { left: 5, top: 20 },   // Clk
                { left: 20, top: 40 }   // Rst
            ],
            out: [
                { left: 75, top: 15 }, { left: 75, top: 25 }, { left: 75, top: 35 }, { left: 75, top: 45 }
            ]
        },
        svgPath: `
            <rect x="2" y="2" width="76" height="56" rx="2" fill="white" stroke="currentColor" stroke-width="2"/>
            <text x="40" y="20" text-anchor="middle" font-family="sans-serif" font-size="10px" font-weight="bold" fill="#1e293b" stroke="none">CNT</text>
            <path d="M 2 25 L 8 28 L 2 31" fill="none" stroke="#64748b" stroke-width="1"/>
            <text x="20" y="50" text-anchor="middle" font-size="8px" fill="#64748b">R</text>
        `,
        logic: (inputs, state) => {
            const clk = inputs[0] || 0;
            const rst = inputs[1] || 0;
            
            if (rst) {
                state.count = 0;
            } else if (clk === 1 && state.lastClk === 0) {
                state.count = ((state.count || 0) + 1) & 0xF;
            }
            state.lastClk = clk;
            
            const val = state.count || 0;
            return {
                out0: val & 1,
                out1: (val >> 1) & 1,
                out2: (val >> 2) & 1,
                out3: (val >> 3) & 1
            };
        }
    },
    'REGISTER_4BIT': {
        name: 'Register 4-bit',
        inputs: 6, // D0-3, Clk, En
        outputs: 4, // Q0-3
        width: 80,
        height: 60,
        labels: { in: ['D0', 'D1', 'D2', 'D3', '>', 'E'], out: ['Q0', 'Q1', 'Q2', 'Q3'] },
        customPorts: {
            in: [
                { left: 10, top: -5 }, { left: 30, top: -5 }, { left: 50, top: -5 }, { left: 70, top: -5 },
                { left: -5, top: 20 }, { left: -5, top: 40 }
            ],
            out: [
                { left: 10, top: 65 }, { left: 30, top: 65 }, { left: 50, top: 65 }, { left: 70, top: 65 }
            ]
        },
        svgPath: `
            <rect x="2" y="2" width="76" height="56" rx="2" fill="white" stroke="currentColor" stroke-width="2"/>
            <text x="40" y="30" text-anchor="middle" font-family="sans-serif" font-size="10px" font-weight="bold" fill="#1e293b" stroke="none">REG</text>
        `,
        logic: (inputs, state) => {
            const d = (inputs[0] || 0) + ((inputs[1] || 0) << 1) + ((inputs[2] || 0) << 2) + ((inputs[3] || 0) << 3);
            const clk = inputs[4] || 0;
            const en = inputs[5] || 0;
            
            if (clk === 1 && state.lastClk === 0 && en) {
                state.val = d;
            }
            state.lastClk = clk;
            
            const val = state.val || 0;
            return {
                out0: val & 1,
                out1: (val >> 1) & 1,
                out2: (val >> 2) & 1,
                out3: (val >> 3) & 1
            };
        }
    },
    'CPU': {
        name: 'CPU 4-bit',
        width: 140,
        height: 100,
        inputs: 6, // D0-3 (Data In), Clk, Rst
        outputs: 9, // A0-3, D0-3 (Data Out), WE
        labels: { 
            in: ['D0', 'D1', 'D2', 'D3', '>', 'R'], 
            out: ['A0', 'A1', 'A2', 'A3', 'Q0', 'Q1', 'Q2', 'Q3', 'WE'] 
        },
        customPorts: {
            in: [
                { left: 135, top: 35 }, { left: 135, top: 45 }, { left: 135, top: 55 }, { left: 135, top: 65 }, // Data In (Right side)
                { left: 60, top: 95 }, { left: 80, top: 95 } // Clk, Rst (Bottom Left)
            ],
            out: [
                { left: -5, top: 35 }, { left: -5, top: 45 }, { left: -5, top: 55 }, { left: -5, top: 65 }, // Addr (Left side)
                { left: 55, top: -5 }, { left: 65, top: -5 }, { left: 75, top: -5 }, { left: 85, top: -5 }, // Data Out (Top)
                { left: 100, top: 95 } // WE (Bottom Right)
            ]
        },
        render: (gate) => {
            const pc = gate.state.pc || 0;
            const acc = gate.state.acc || 0;
            const phase = gate.state.phase || 0;
            return `
                <div class="w-[140px] h-[100px] bg-slate-800 dark:bg-slate-900 border-2 border-slate-600 dark:border-slate-500 rounded flex flex-col items-center justify-center relative text-white">
                    <div class="text-[14px] font-bold text-amber-400 mb-2">CPU 4-bit</div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 mt-1 w-full px-4">
                        <div class="text-[10px] text-slate-400">PC</div>
                        <div class="text-[10px] font-mono text-right text-green-400">${pc.toString(16).toUpperCase()}</div>
                        <div class="text-[10px] text-slate-400">ACC</div>
                        <div class="text-[10px] font-mono text-right text-blue-400">${acc.toString(16).toUpperCase()}</div>
                        <div class="text-[10px] text-slate-400">PHASE</div>
                        <div class="text-[10px] font-mono text-right text-purple-400">${phase}</div>
                    </div>
                </div>
            `;
        },
        logic: (inputs, state) => {
            const dataIn = (inputs[0] || 0) + ((inputs[1] || 0) << 1) + ((inputs[2] || 0) << 2) + ((inputs[3] || 0) << 3);
            const clk = inputs[4] || 0;
            const rst = inputs[5] || 0;
            
            if (rst) {
                state.pc = 0;
                state.acc = 0;
                state.phase = 0;
                state.opcode = 0;
                state.addrOut = 0;
                state.dataOut = 0;
                state.we = 0;
            } else if (clk === 1 && state.lastClk === 0) {
                if (state.phase === undefined) state.phase = 0;
                
                if (state.phase === 0) {
                    // T0: Fetch Opcode
                    state.addrOut = state.pc;
                    state.we = 0;
                    state.phase = 1;
                } else if (state.phase === 1) {
                    // T1: Latch Opcode, Fetch Operand
                    state.opcode = dataIn;
                    state.addrOut = (state.pc + 1) & 0xF;
                    state.phase = 2;
                } else if (state.phase === 2) {
                    // T2: Execute
                    const operand = dataIn;
                    const op = state.opcode;
                    
                    switch(op) {
                        case 0: break; // NOP
                        case 1: state.acc = operand; break; // LDI
                        case 2: state.acc = (state.acc + operand) & 0xF; break; // ADD
                        case 3: // STA [addr]
                            state.addrOut = operand;
                            state.dataOut = state.acc;
                            state.we = 1;
                            break;
                        case 4: state.pc = (operand - 2) & 0xF; break; // JMP
                    }
                    
                    if (op !== 3) state.we = 0;
                    state.pc = (state.pc + 2) & 0xF;
                    
                    state.phase = (op === 3) ? 3 : 0;
                } else if (state.phase === 3) {
                    state.we = 0;
                    state.phase = 0;
                }
            }
            state.lastClk = clk;
            
            return {
                out0: state.addrOut & 1,
                out1: (state.addrOut >> 1) & 1,
                out2: (state.addrOut >> 2) & 1,
                out3: (state.addrOut >> 3) & 1,
                out4: state.dataOut & 1,
                out5: (state.dataOut >> 1) & 1,
                out6: (state.dataOut >> 2) & 1,
                out7: (state.dataOut >> 3) & 1,
                out8: state.we
            };
        }
    },
    'PROBE': {
        name: 'Oscilloscope Probe',
        inputs: 1,
        outputs: 0,
        color: 'text-amber-500',
        render: (gate) => `<div class="flex items-center justify-center w-full h-full border-2 border-amber-500 rounded bg-amber-50 text-amber-600 font-bold"><span class="material-symbols-rounded text-[16px]">query_stats</span></div>`,
        logic: (inputs, state) => {
            state.value = inputs[0] || 0;
            return {};
        }
    },
    'HEX_DISPLAY': {
        name: 'HEX Display',
        width: 40,
        height: 50,
        inputs: 4, // 8, 4, 2, 1
        outputs: 0,
        labels: { in: ['8', '4', '2', '1'] },
        render: (gate) => {
            // Segments: a(top), b(tr), c(br), d(bottom), e(bl), f(tl), g(mid)
            return `
            <div class="hex-display relative w-[40px] h-[50px] bg-slate-900 rounded p-1">
                <svg viewBox="0 0 10 18" class="w-full h-full overflow-visible">
                    <g class="segments" stroke="none">
                        <!-- a --> <path id="seg-a-${gate.id}" d="M 2 1 L 8 1 L 9 2 L 8 3 L 2 3 L 1 2 Z" fill="#334155" />
                        <!-- b --> <path id="seg-b-${gate.id}" d="M 9 2 L 10 3 L 10 8 L 9 9 L 8 8 L 8 3 Z" fill="#334155" />
                        <!-- c --> <path id="seg-c-${gate.id}" d="M 9 9 L 10 10 L 10 15 L 9 16 L 8 15 L 8 10 Z" fill="#334155" />
                        <!-- d --> <path id="seg-d-${gate.id}" d="M 8 15 L 9 16 L 8 17 L 2 17 L 1 16 L 2 15 Z" fill="#334155" />
                        <!-- e --> <path id="seg-e-${gate.id}" d="M 2 15 L 1 16 L 0 15 L 0 10 L 1 9 L 2 10 Z" fill="#334155" />
                        <!-- f --> <path id="seg-f-${gate.id}" d="M 2 3 L 1 2 L 0 3 L 0 8 L 1 9 L 2 8 Z" fill="#334155" />
                        <!-- g --> <path id="seg-g-${gate.id}" d="M 2 8 L 8 8 L 9 9 L 8 10 L 2 10 L 1 9 Z" fill="#334155" />
                    </g>
                </svg>
            </div>`;
        },
        logic: (inputs, state) => {
            const val = (inputs[0] * 8) + (inputs[1] * 4) + (inputs[2] * 2) + (inputs[3] * 1);
            state.value = val;
            return {};
        }
    },
    'LABEL': {
        name: 'Label',
        inputs: 0,
        outputs: 0,
        width: 100,
        height: 30,
        render: (gate) => {
            const text = gate.state.text || 'Tekst';
            return `
                <div class="flex items-center justify-center w-full h-full px-2 py-1 bg-transparent border border-dashed border-slate-300 dark:border-slate-600 rounded hover:border-primary transition-colors cursor-text select-none" title="Kliknij dwukrotnie aby edytować">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap overflow-hidden text-ellipsis">${text}</span>
                </div>
            `;
        },
        logic: () => ({})
    }
};

// --- History (Undo/Redo) ---

function saveState() {
    // Remove future history if we are in the middle
    if (STATE.historyIndex < STATE.history.length - 1) {
        STATE.history = STATE.history.slice(0, STATE.historyIndex + 1);
    }

    // Create deep copy of current state
    const snapshot = {
        gates: JSON.parse(JSON.stringify(STATE.gates)),
        connections: JSON.parse(JSON.stringify(STATE.connections)),
        nextId: STATE.nextId
    };

    STATE.history.push(snapshot);
    if (STATE.history.length > STATE.maxHistory) {
        STATE.history.shift();
    } else {
        STATE.historyIndex++;
    }

    updateHistoryButtons();
}

function undo() {
    if (STATE.historyIndex > 0) {
        STATE.historyIndex--;
        loadStateFromHistory(STATE.history[STATE.historyIndex]);
        updateHistoryButtons();
    }
}

function redo() {
    if (STATE.historyIndex < STATE.history.length - 1) {
        STATE.historyIndex++;
        loadStateFromHistory(STATE.history[STATE.historyIndex]);
        updateHistoryButtons();
    }
}

function loadStateFromHistory(snapshot) {
    STATE.gates = JSON.parse(JSON.stringify(snapshot.gates));
    STATE.connections = JSON.parse(JSON.stringify(snapshot.connections));
    STATE.nextId = snapshot.nextId;

    // Re-render everything
    gatesLayer.innerHTML = '';
    connectionsLayer.innerHTML = '';
    
    STATE.gates.forEach(gate => renderGate(gate));
    renderConnections();
    updateStats();
    propagateSignals();
}

function updateHistoryButtons() {
    const undoBtn = document.getElementById('undo-btn');
    const redoBtn = document.getElementById('redo-btn');
    
    if (undoBtn) {
        undoBtn.disabled = STATE.historyIndex <= 0;
        undoBtn.classList.toggle('opacity-50', STATE.historyIndex <= 0);
    }
    
    if (redoBtn) {
        redoBtn.disabled = STATE.historyIndex >= STATE.history.length - 1;
        redoBtn.classList.toggle('opacity-50', STATE.historyIndex >= STATE.history.length - 1);
    }
}

// --- DOM Elements ---

const canvas = document.getElementById('canvas-container');
const world = document.getElementById('world');
const gatesLayer = document.getElementById('gates-layer');
const connectionsLayer = document.getElementById('connections-layer');
const statsGates = document.getElementById('stats-gates');
const statsConnections = document.getElementById('stats-connections');

// --- Mobile Interactions Setup ---

function setupMobileInteractions() {
    const leftPanel = document.getElementById('left-panel');
    const rightPanel = document.getElementById('right-panel');
    const overlay = document.getElementById('mobile-overlay');
    
    function closeAll() {
        leftPanel.classList.add('-translate-x-full');
        rightPanel.classList.add('translate-x-full');
        overlay.classList.add('hidden');
    }

    document.getElementById('mobile-gates-btn')?.addEventListener('click', () => {
        leftPanel.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    });

    document.getElementById('close-left-panel')?.addEventListener('click', closeAll);

    document.getElementById('mobile-info-btn')?.addEventListener('click', () => {
        rightPanel.classList.remove('translate-x-full');
        overlay.classList.remove('hidden');
    });

    document.getElementById('close-right-panel')?.addEventListener('click', closeAll);
    
    overlay?.addEventListener('click', closeAll);
}

// --- Cedar Logic Converter Setup ---

function setupCedarConverter() {
    const modal = document.getElementById('cedar-modal');
    const btn = document.getElementById('export-cedar-btn');
    const closeBtn = document.getElementById('close-cedar-btn');
    const dropZone = document.getElementById('cedar-drop-zone');
    const fileInput = document.getElementById('cedar-file-input');
    const fileInfo = document.getElementById('cedar-file-info');
    const fileName = document.getElementById('cedar-filename');
    const removeFileBtn = document.getElementById('cedar-remove-file');
    const convertBtn = document.getElementById('cedar-convert-btn');
    
    let currentFile = null;

    if (!btn) return;

    function openModal() {
        modal.classList.remove('hidden');
    }
    
    function closeModal() {
        modal.classList.add('hidden');
        resetFile();
    }
    
    function resetFile() {
        currentFile = null;
        fileInput.value = '';
        fileInfo.classList.add('hidden');
        dropZone.classList.remove('hidden');
        convertBtn.disabled = true;
    }
    
    function handleFile(file) {
        if (file && file.name.endsWith('.json')) {
            currentFile = file;
            fileName.innerText = file.name;
            dropZone.classList.add('hidden');
            fileInfo.classList.remove('hidden');
            convertBtn.disabled = false;
        } else {
            alert('Proszę wybrać plik .json');
        }
    }

    btn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    dropZone.addEventListener('click', () => fileInput.click());
    
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('border-primary', 'bg-slate-50', 'dark:bg-slate-700');
    });
    
    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-primary', 'bg-slate-50', 'dark:bg-slate-700');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-primary', 'bg-slate-50', 'dark:bg-slate-700');
        if (e.dataTransfer.files.length > 0) {
            handleFile(e.dataTransfer.files[0]);
        }
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleFile(e.target.files[0]);
        }
    });

    removeFileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        resetFile();
    });

    convertBtn.addEventListener('click', () => {
        if (!currentFile) return;
        
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const data = JSON.parse(e.target.result);
                const cedarXml = convertToCedarLogic(data);
                
                const blob = new Blob([cedarXml], { type: 'application/xml' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = currentFile.name.replace('.json', '.cdl');
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                closeModal();
            } catch (err) {
                console.error(err);
                alert('Błąd podczas konwersji!');
            }
        };
        reader.readAsText(currentFile);
    });
}

function convertToCedarLogic(data) {
    // Helper to map types
    const typeMap = {
        'INPUT': 'TOGGLE_SWITCH',
        'OUTPUT': 'LED',
        'AND': 'AND2',
        'OR': 'OR2',
        'NOT': 'INV',
        'NAND': 'NAND2',
        'NOR': 'NOR2',
        'XOR': 'XOR2',
        'XNOR': 'XNOR2',
        'BUFFER': 'BUFFER',
        'CLOCK': 'CLOCK',
        'D_FF': 'D_FLIP_FLOP',
        'JK_FF': 'JK_FLIP_FLOP'
    };

    let xml = '<?xml version="1.0" encoding="UTF-8"?>\n';
    xml += '<cedar-logic-circuit>\n';
    
    // Gates
    data.gates.forEach(gate => {
        const cedarType = typeMap[gate.type];
        if (cedarType) {
            xml += `  <component id="${gate.id}" type="${cedarType}" x="${gate.x}" y="${gate.y}" />\n`;
        }
    });
    
    // Connections
    data.connections.forEach(conn => {
        // Check if both gates exist and are supported
        const fromGate = data.gates.find(g => g.id === conn.from.gateId);
        const toGate = data.gates.find(g => g.id === conn.to.gateId);
        
        if (fromGate && toGate && typeMap[fromGate.type] && typeMap[toGate.type]) {
            xml += `  <connection from_gate="${conn.from.gateId}" from_pin="${conn.from.portIndex}" to_gate="${conn.to.gateId}" to_pin="${conn.to.portIndex}" />\n`;
        }
    });
    
    xml += '</cedar-logic-circuit>';
    return xml;
}

// --- Missing Functions Restored ---

function setupCanvasInteractions() {
    // Panning
    canvas.addEventListener('mousedown', (e) => {
        if (e.target === canvas || e.target === world || e.target.id === 'connections-layer') {
            STATE.isPanning = true;
            STATE.panStart = { x: e.clientX, y: e.clientY };
            canvas.style.cursor = 'grabbing';
            
            // If wiring, cancel it
            if (STATE.connectionMode) {
                STATE.connectionMode = null;
                STATE.tempConnection = null;
                renderConnections();
            }
        }
    });

    window.addEventListener('mousemove', (e) => {
        // Panning
        if (STATE.isPanning) {
            const dx = e.clientX - STATE.panStart.x;
            const dy = e.clientY - STATE.panStart.y;
            
            STATE.view.x += dx;
            STATE.view.y += dy;
            STATE.panStart = { x: e.clientX, y: e.clientY };
            updateTransform();
            return;
        }

        // Gate Dragging
        if (STATE.isDragging && STATE.draggedGateId) {
            const coords = toWorldCoordinates(e.clientX, e.clientY);
            
            const gate = STATE.gates.find(g => g.id === STATE.draggedGateId);
            if (gate) {
                gate.x = coords.x - STATE.dragOffset.x;
                gate.y = coords.y - STATE.dragOffset.y;
                const el = document.getElementById(`gate-${gate.id}`);
                el.style.left = `${gate.x}px`;
                el.style.top = `${gate.y}px`;
                renderConnections(); 
            }
        } 
        
        // Wiring / Connection Dragging
        if (STATE.tempConnection) {
            const coords = toWorldCoordinates(e.clientX, e.clientY);
            STATE.tempConnection.x = coords.x;
            STATE.tempConnection.y = coords.y;
            renderConnections();
        }
    });

    window.addEventListener('mouseup', (e) => {
        STATE.isPanning = false;
        canvas.style.cursor = 'crosshair';

        if (STATE.isDragging && STATE.draggedGateId) {
            const gate = STATE.gates.find(g => g.id === STATE.draggedGateId);
            if (gate) {
                gate.x = snapToGrid(gate.x);
                gate.y = snapToGrid(gate.y);
                const el = document.getElementById(`gate-${gate.id}`);
                el.style.left = `${gate.x}px`;
                el.style.top = `${gate.y}px`;
                el.classList.remove('dragging');
                renderConnections();
            }
            STATE.isDragging = false;
            STATE.draggedGateId = null;
        }
        
        if (STATE.tempConnection) {
            // If we were dragging a connection
            if (STATE.connectionMode === 'drag') {
                if (e.target.classList.contains('port')) {
                    // Dropped on a port -> Finish
                    finishConnection(e.target);
                    STATE.tempConnection = null;
                    STATE.connectionMode = null;
                } else {
                    // Dropped elsewhere
                    // Check if it was a click (short distance)
                    const dist = Math.hypot(e.clientX - STATE.dragStartPos.x, e.clientY - STATE.dragStartPos.y);
                    if (dist < 5) {
                        // It was a click -> Switch to Click Mode
                        STATE.connectionMode = 'click';
                    } else {
                        // It was a drag to nowhere -> Cancel
                        STATE.tempConnection = null;
                        STATE.connectionMode = null;
                    }
                }
                renderConnections();
            }
        }
    });

    // Global click to cancel click-mode connection if clicking on empty space
    window.addEventListener('mousedown', (e) => {
        if (STATE.connectionMode === 'click' && !e.target.classList.contains('port')) {
            STATE.tempConnection = null;
            STATE.connectionMode = null;
            renderConnections();
        }
    });

    // ROM Edit
    window.addEventListener('dblclick', (e) => {
        const gateEl = e.target.closest('.gate-component');
        if (gateEl) {
            const id = parseInt(gateEl.dataset.id);
            const gate = STATE.gates.find(g => g.id === id);
            if (gate && gate.type === 'ROM') {
                const current = (gate.state.memory || []).map(v => v.toString(16).toUpperCase()).join(',');
                const input = prompt('Wprowadź 8 wartości HEX oddzielonych przecinkami (np. 0,1,A,F):', current);
                if (input) {
                    const values = input.split(',').map(v => parseInt(v.trim(), 16));
                    if (values.length === 8 && values.every(v => !isNaN(v))) {
                        gate.state.memory = values;
                        updateGateVisuals(gate.id);
                        propagateSignals();
                    } else {
                        alert('Błąd! Wymagane 8 liczb szesnastkowych.');
                    }
                }
            }
        }
    });
}

function setupZoomControls() {
    // Wheel Zoom
    canvas.addEventListener('wheel', (e) => {
        e.preventDefault();
        const zoomIntensity = 0.1;
        const direction = e.deltaY > 0 ? -1 : 1;
        const factor = 1 + (direction * zoomIntensity);
        
        // Zoom towards mouse pointer
        const rect = canvas.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;
        
        // Calculate world coordinates before zoom
        const worldX = (mouseX - STATE.view.x) / STATE.view.zoom;
        const worldY = (mouseY - STATE.view.y) / STATE.view.zoom;
        
        // Apply zoom
        let newZoom = STATE.view.zoom * factor;
        newZoom = Math.max(0.1, Math.min(newZoom, 5)); // Limits
        
        // Calculate new view position to keep mouse over same world point
        STATE.view.x = mouseX - worldX * newZoom;
        STATE.view.y = mouseY - worldY * newZoom;
        STATE.view.zoom = newZoom;
        
        updateTransform();
    });

    // Buttons
    document.getElementById('zoom-in').addEventListener('click', () => {
        STATE.view.zoom = Math.min(STATE.view.zoom * 1.2, 5);
        updateTransform();
    });
    document.getElementById('zoom-out').addEventListener('click', () => {
        STATE.view.zoom = Math.max(STATE.view.zoom / 1.2, 0.1);
        updateTransform();
    });
    document.getElementById('zoom-reset').addEventListener('click', () => {
        STATE.view.zoom = 1;
        centerView();
        updateTransform();
    });
}

function setupChat() {
    const messagesContainer = document.getElementById('chat-messages');
    const input = document.getElementById('chat-input');
    const sendBtn = document.getElementById('chat-send-btn');

    if (!messagesContainer || !input || !sendBtn) return;

    function addMessage(text, sender) {
        const div = document.createElement('div');
        div.className = `flex gap-3 ${sender === 'user' ? 'flex-row-reverse' : ''}`;
        
        if (sender === 'bot') {
            div.innerHTML = `
                <div class="w-8 h-8 rounded-full bg-primary/10 flex-shrink-0 flex items-center justify-center mt-1">
                    <span class="material-symbols-rounded text-primary text-xs">smart_toy</span>
                </div>
                <div class="bg-slate-100 dark:bg-slate-800 p-3 rounded-2xl rounded-tl-none text-sm text-slate-700 dark:text-slate-300 max-w-[85%]">
                    ${text}
                </div>
            `;
        } else {
            div.innerHTML = `
                <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900 flex-shrink-0 flex items-center justify-center mt-1">
                    <span class="material-symbols-rounded text-primary dark:text-indigo-300 text-xs">person</span>
                </div>
                <div class="bg-primary text-white p-3 rounded-2xl rounded-tr-none text-sm max-w-[85%]">
                    ${text}
                </div>
            `;
        }
        
        messagesContainer.appendChild(div);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function handleSend() {
        const text = input.value.trim();
        if (!text) return;

        addMessage(text, 'user');
        input.value = '';

        // Simulate thinking delay
        setTimeout(() => {
            let responseHtml = "Nie rozumiem pytania.";
            if (typeof Chatbot !== 'undefined') {
                 const chatbot = new Chatbot();
                 const responseObj = chatbot.getResponse(text);
                 responseHtml = `<strong>${responseObj.title}</strong><br>${responseObj.content}`;
            } else {
                 responseHtml = "Chatbot nie jest dostępny.";
            }
            addMessage(responseHtml, 'bot');
        }, 400);
    }

    sendBtn.addEventListener('click', handleSend);
    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') handleSend();
    });
}

function renderOscilloscope() {
    const canvas = document.getElementById('oscilloscope-canvas');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    const w = canvas.width = canvas.clientWidth;
    const h = canvas.height = canvas.clientHeight;
    
    ctx.fillStyle = '#0f172a'; // slate-900
    ctx.fillRect(0, 0, w, h);
    
    // Grid
    ctx.strokeStyle = '#1e293b';
    ctx.lineWidth = 1;
    ctx.beginPath();
    for (let x = 0; x < w; x += 20) { ctx.moveTo(x, 0); ctx.lineTo(x, h); }
    for (let y = 0; y < h; y += 20) { ctx.moveTo(0, y); ctx.lineTo(w, y); }
    ctx.stroke();
    
    const history = STATE.oscilloscope.history;
    
    // Find all probes
    const probes = STATE.gates.filter(g => g.type === 'PROBE');
    if (probes.length === 0) {
        ctx.fillStyle = '#64748b';
        ctx.font = '12px sans-serif';
        ctx.fillText('Dodaj sondę (PROBE) aby zobaczyć wykres', 10, 20);
        return;
    }
    
    const stepX = w / STATE.oscilloscope.maxHistory;
    const rowHeight = Math.min(h / probes.length, 60);
    
    probes.forEach((probe, idx) => {
        const yBase = idx * rowHeight + rowHeight / 2 + 10;
        
        // Label
        ctx.fillStyle = '#fbbf24'; // amber-400
        ctx.font = '10px monospace';
        ctx.fillText(`Probe ${probe.id}`, 5, yBase - 15);
        
        // Draw Line
        ctx.strokeStyle = '#fbbf24';
        ctx.lineWidth = 2;
        ctx.beginPath();
        
        if (history.length > 0) {
            history.forEach((snap, i) => {
                const val = snap[probe.id] || 0;
                const x = i * stepX;
                const y = yBase + (val ? -10 : 10);
                
                if (i === 0) ctx.moveTo(x, y);
                else {
                    // Square wave style
                    const prevVal = history[i-1][probe.id] || 0;
                    const prevY = yBase + (prevVal ? -10 : 10);
                    ctx.lineTo(x, prevY);
                    ctx.lineTo(x, y);
                }
            });
        }
        ctx.stroke();
    });
}

// --- Shortcuts Setup ---

function setupShortcuts() {
    window.addEventListener('keydown', (e) => {
        if (e.ctrlKey || e.metaKey) {
            if (e.key === 'z') {
                e.preventDefault();
                undo();
            } else if (e.key === 'y') {
                e.preventDefault();
                redo();
            } else if (e.key === 's') {
                e.preventDefault();
                saveWorkspace();
            }
        }
        if (e.key === 'Delete' || e.key === 'Backspace') {
            if (STATE.selectedGateId) {
                removeGate(STATE.selectedGateId);
                STATE.selectedGateId = null;
            }
        }
    });
}

// --- Examples, Truth Table, Export Setup ---

function setupExamples() {
    const modal = document.getElementById('examples-modal');
    const btn = document.getElementById('examples-btn');
    const closeBtn = document.getElementById('close-examples-btn');
    const list = document.getElementById('examples-list');

    if (!btn || !modal) return;

    btn.addEventListener('click', () => {
        modal.classList.remove('hidden');
        renderExamples();
    });

    closeBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
    });

    const EXAMPLES = [
        {
            name: 'Półsumator (Half Adder)',
            description: 'Dodawanie dwóch bitów (A+B) z przeniesieniem.',
            data: {
                gates: [
                    { id: 1, type: 'INPUT', x: 100, y: 100, inputs: [], outputs: [0], state: { value: 0 } },
                    { id: 2, type: 'INPUT', x: 100, y: 200, inputs: [], outputs: [0], state: { value: 0 } },
                    { id: 3, type: 'XOR', x: 300, y: 100, inputs: [0, 0], outputs: [0], state: { value: 0 } },
                    { id: 4, type: 'AND', x: 300, y: 200, inputs: [0, 0], outputs: [0], state: { value: 0 } },
                    { id: 5, type: 'OUTPUT', x: 500, y: 100, inputs: [0], outputs: [], state: { value: 0 } },
                    { id: 6, type: 'OUTPUT', x: 500, y: 200, inputs: [0], outputs: [], state: { value: 0 } }
                ],
                connections: [
                    { from: { gateId: 1, portIndex: 0 }, to: { gateId: 3, portIndex: 0 } },
                    { from: { gateId: 2, portIndex: 0 }, to: { gateId: 3, portIndex: 1 } },
                    { from: { gateId: 1, portIndex: 0 }, to: { gateId: 4, portIndex: 0 } },
                    { from: { gateId: 2, portIndex: 0 }, to: { gateId: 4, portIndex: 1 } },
                    { from: { gateId: 3, portIndex: 0 }, to: { gateId: 5, portIndex: 0 } },
                    { from: { gateId: 4, portIndex: 0 }, to: { gateId: 6, portIndex: 0 } }
                ],
                nextId: 7
            }
        },
        {
            name: 'Pełny Sumator (Full Adder)',
            description: 'Dodawanie trzech bitów (A+B+Cin) z użyciem dwóch półsumatorów.',
            data: {
                gates: [
                    { id: 1, type: 'INPUT', x: 60, y: 80, inputs: [], outputs: [0], state: { value: 0 } }, // A
                    { id: 2, type: 'INPUT', x: 60, y: 160, inputs: [], outputs: [0], state: { value: 0 } }, // B
                    { id: 3, type: 'INPUT', x: 60, y: 240, inputs: [], outputs: [0], state: { value: 0 } }, // Cin
                    { id: 4, type: 'HALF_ADDER', x: 200, y: 100, inputs: [0, 0], outputs: [0, 0], state: { value: 0 } },
                    { id: 5, type: 'HALF_ADDER', x: 360, y: 180, inputs: [0, 0], outputs: [0, 0], state: { value: 0 } },
                    { id: 6, type: 'OR', x: 500, y: 280, inputs: [0, 0], outputs: [0], state: { value: 0 } },
                    { id: 7, type: 'OUTPUT', x: 600, y: 180, inputs: [0], outputs: [], state: { value: 0 } }, // Sum
                    { id: 8, type: 'OUTPUT', x: 600, y: 280, inputs: [0], outputs: [], state: { value: 0 } }  // Cout
                ],
                connections: [
                    { from: { gateId: 1, portIndex: 0 }, to: { gateId: 4, portIndex: 0 } },
                    { from: { gateId: 2, portIndex: 0 }, to: { gateId: 4, portIndex: 1 } },
                    { from: { gateId: 4, portIndex: 0 }, to: { gateId: 5, portIndex: 0 } }, // HA1 Sum -> HA2 A
                    { from: { gateId: 3, portIndex: 0 }, to: { gateId: 5, portIndex: 1 } }, // Cin -> HA2 B
                    { from: { gateId: 4, portIndex: 1 }, to: { gateId: 6, portIndex: 0 } }, // HA1 Carry -> OR A
                    { from: { gateId: 5, portIndex: 1 }, to: { gateId: 6, portIndex: 1 } }, // HA2 Carry -> OR B
                    { from: { gateId: 5, portIndex: 0 }, to: { gateId: 7, portIndex: 0 } }, // HA2 Sum -> Sum
                    { from: { gateId: 6, portIndex: 0 }, to: { gateId: 8, portIndex: 0 } }  // OR Out -> Cout
                ],
                nextId: 9
            }
        },
        {
            name: 'Przerzutnik RS (NAND)',
            description: 'Podstawowa komórka pamięci zbudowana z bramek NAND.',
            data: {
                gates: [
                    { id: 1, type: 'INPUT', x: 100, y: 100, inputs: [], outputs: [0], state: { value: 1 } }, // S (active low)
                    { id: 2, type: 'INPUT', x: 100, y: 200, inputs: [], outputs: [0], state: { value: 1 } }, // R (active low)
                    { id: 3, type: 'NAND', x: 300, y: 100, inputs: [0, 0], outputs: [0], state: { value: 0 } },
                    { id: 4, type: 'NAND', x: 300, y: 200, inputs: [0, 0], outputs: [0], state: { value: 0 } },
                    { id: 5, type: 'OUTPUT', x: 500, y: 100, inputs: [0], outputs: [], state: { value: 0 } },
                    { id: 6, type: 'OUTPUT', x: 500, y: 200, inputs: [0], outputs: [], state: { value: 0 } }
                ],
                connections: [
                    { from: { gateId: 1, portIndex: 0 }, to: { gateId: 3, portIndex: 0 } },
                    { from: { gateId: 2, portIndex: 0 }, to: { gateId: 4, portIndex: 1 } },
                    { from: { gateId: 3, portIndex: 0 }, to: { gateId: 4, portIndex: 0 } }, // Feedback
                    { from: { gateId: 4, portIndex: 0 }, to: { gateId: 3, portIndex: 1 } }, // Feedback
                    { from: { gateId: 3, portIndex: 0 }, to: { gateId: 5, portIndex: 0 } },
                    { from: { gateId: 4, portIndex: 0 }, to: { gateId: 6, portIndex: 0 } }
                ],
                nextId: 7
            }
        },
        {
            name: 'Licznik 4-bitowy',
            description: 'Licznik zliczający impulsy zegara, wyświetlany na wyświetlaczu HEX.',
            data: {
                gates: [
                    { id: 1, type: 'CLOCK', x: 100, y: 100, inputs: [], outputs: [0], state: { value: 0 } },
                    { id: 2, type: 'INPUT', x: 100, y: 200, inputs: [], outputs: [0], state: { value: 0 } }, // Reset
                    { id: 3, type: 'COUNTER_4BIT', x: 300, y: 150, inputs: [0, 0], outputs: [0, 0, 0, 0], state: { count: 0 } },
                    { id: 4, type: 'HEX_DISPLAY', x: 500, y: 150, inputs: [0, 0, 0, 0], outputs: [], state: { value: 0 } }
                ],
                connections: [
                    { from: { gateId: 1, portIndex: 0 }, to: { gateId: 3, portIndex: 0 } }, // Clk
                    { from: { gateId: 2, portIndex: 0 }, to: { gateId: 3, portIndex: 1 } }, // Rst
                    { from: { gateId: 3, portIndex: 3 }, to: { gateId: 4, portIndex: 0 } }, // Q3 -> 8
                    { from: { gateId: 3, portIndex: 2 }, to: { gateId: 4, portIndex: 1 } }, // Q2 -> 4
                    { from: { gateId: 3, portIndex: 1 }, to: { gateId: 4, portIndex: 2 } }, // Q1 -> 2
                    { from: { gateId: 3, portIndex: 0 }, to: { gateId: 4, portIndex: 3 } }  // Q0 -> 1
                ],
                nextId: 5
            }
        },
        {
            name: 'Symulacja Sygnalizacji Świetlnej',
            description: 'Prosty układ sekwencyjny sterujący 3 diodami (Czerwone, Żółte, Zielone).',
            data: {
                gates: [
                    { id: 1, type: 'CLOCK', x: 50, y: 100, inputs: [], outputs: [0], state: { value: 0 } },
                    { id: 2, type: 'COUNTER_4BIT', x: 200, y: 100, inputs: [0, 0], outputs: [0, 0, 0, 0], state: { count: 0 } },
                    // Logic for Red (Q1' Q0') -> 00
                    { id: 3, type: 'NOT', x: 350, y: 50, inputs: [0], outputs: [0], state: { value: 0 } },
                    { id: 4, type: 'NOT', x: 350, y: 100, inputs: [0], outputs: [0], state: { value: 0 } },
                    { id: 5, type: 'AND', x: 450, y: 75, inputs: [0, 0], outputs: [0], state: { value: 0 } },
                    // Logic for Yellow (Q0) -> 01 or 11 (simplified)
                    // Logic for Green (Q1) -> 10 or 11 (simplified)
                    { id: 6, type: 'OUTPUT', x: 600, y: 75, inputs: [0], outputs: [], state: { value: 0 } }, // Red
                    { id: 7, type: 'OUTPUT', x: 600, y: 150, inputs: [0], outputs: [], state: { value: 0 } }, // Yellow (Q0)
                    { id: 8, type: 'OUTPUT', x: 600, y: 225, inputs: [0], outputs: [], state: { value: 0 } }, // Green (Q1)
                    { id: 9, type: 'LABEL', x: 650, y: 75, inputs: [], outputs: [], state: { text: 'RED' } },
                    { id: 10, type: 'LABEL', x: 650, y: 150, inputs: [], outputs: [], state: { text: 'YEL' } },
                    { id: 11, type: 'LABEL', x: 650, y: 225, inputs: [], outputs: [], state: { text: 'GRN' } }
                ],
                connections: [
                    { from: { gateId: 1, portIndex: 0 }, to: { gateId: 2, portIndex: 0 } }, // Clk -> Counter
                    // Red Logic: !Q1 AND !Q0 (State 0)
                    { from: { gateId: 2, portIndex: 1 }, to: { gateId: 3, portIndex: 0 } }, // Q1 -> NOT
                    { from: { gateId: 2, portIndex: 0 }, to: { gateId: 4, portIndex: 0 } }, // Q0 -> NOT
                    { from: { gateId: 3, portIndex: 0 }, to: { gateId: 5, portIndex: 0 } },
                    { from: { gateId: 4, portIndex: 0 }, to: { gateId: 5, portIndex: 1 } },
                    { from: { gateId: 5, portIndex: 0 }, to: { gateId: 6, portIndex: 0 } }, // AND -> Red
                    // Yellow Logic: Just Q0 (State 1 and 3) - simplified
                    { from: { gateId: 2, portIndex: 0 }, to: { gateId: 7, portIndex: 0 } },
                    // Green Logic: Just Q1 (State 2 and 3) - simplified
                    { from: { gateId: 2, portIndex: 1 }, to: { gateId: 8, portIndex: 0 } }
                ],
                nextId: 12
            }
        }
    ];

    function renderExamples() {
        list.innerHTML = '';
        EXAMPLES.forEach(ex => {
            const item = document.createElement('div');
            item.className = 'p-4 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl hover:border-primary dark:hover:border-primary cursor-pointer transition-all group';
            item.innerHTML = `
                <h3 class="font-bold text-slate-800 dark:text-white mb-1 group-hover:text-primary transition-colors">${ex.name}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">${ex.description}</p>
            `;
            item.addEventListener('click', () => {
                loadStateFromHistory(ex.data); // Reuse history loader as it does exactly what we need
                modal.classList.add('hidden');
                saveState(); // Save this new state to history
            });
            list.appendChild(item);
        });
    }
}

function setupTruthTable() {
    const modal = document.getElementById('truth-table-modal');
    const btn = document.getElementById('truth-table-btn');
    const closeBtn = document.getElementById('close-tt-btn');
    const thead = document.getElementById('tt-header');
    const tbody = document.getElementById('tt-body');
    const emptyMsg = document.getElementById('tt-empty-msg');

    if (!btn || !modal) return;

    btn.addEventListener('click', () => {
        modal.classList.remove('hidden');
        generateTruthTable();
    });

    closeBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
    });

    function generateTruthTable() {
        // Find Inputs and Outputs
        const inputs = STATE.gates.filter(g => g.type === 'INPUT').sort((a, b) => a.y - b.y);
        const outputs = STATE.gates.filter(g => g.type === 'OUTPUT').sort((a, b) => a.y - b.y);

        if (inputs.length === 0 || outputs.length === 0) {
            thead.innerHTML = '';
            tbody.innerHTML = '';
            emptyMsg.classList.remove('hidden');
            return;
        }
        emptyMsg.classList.add('hidden');

        // Generate Header
        let headerHtml = '';
        inputs.forEach((inp, i) => headerHtml += `<th class="px-4 py-3 bg-slate-100 dark:bg-slate-800">In ${i}</th>`);
        headerHtml += '<th class="px-4 py-3 bg-slate-200 dark:bg-slate-700 w-px"></th>'; // Separator
        outputs.forEach((out, i) => headerHtml += `<th class="px-4 py-3 bg-indigo-50 dark:bg-slate-800 text-primary">Out ${i}</th>`);
        thead.innerHTML = headerHtml;

        // Generate Rows (2^n combinations)
        // Limit to 6 inputs (64 rows) to prevent freezing
        if (inputs.length > 6) {
            tbody.innerHTML = '<tr><td colspan="100" class="p-4 text-center text-red-500">Zbyt wiele wejść (max 6).</td></tr>';
            return;
        }

        // Save current state to restore later
        const savedState = inputs.map(g => g.state.value);
        
        tbody.innerHTML = '';
        const combinations = 1 << inputs.length;

        for (let i = 0; i < combinations; i++) {
            // Set inputs
            inputs.forEach((inp, bit) => {
                // bit 0 is LSB (last input in array usually, but here we sorted by Y)
                // Let's treat last input as LSB
                const val = (i >> (inputs.length - 1 - bit)) & 1;
                inp.state.value = val;
            });

            // Propagate
            propagateSignals();

            // Read outputs
            const row = document.createElement('tr');
            row.className = 'bg-white dark:bg-slate-800 border-b dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700';
            
            let rowHtml = '';
            inputs.forEach(inp => rowHtml += `<td class="px-4 py-2 font-mono text-center ${inp.state.value ? 'text-green-600 font-bold' : 'text-slate-400'}">${inp.state.value}</td>`);
            rowHtml += '<td class="bg-slate-100 dark:bg-slate-700"></td>';
            outputs.forEach(out => rowHtml += `<td class="px-4 py-2 font-mono text-center ${out.state.value ? 'text-primary font-bold' : 'text-slate-400'}">${out.state.value}</td>`);
            
            row.innerHTML = rowHtml;
            tbody.appendChild(row);
        }

        // Restore state
        inputs.forEach((inp, i) => inp.state.value = savedState[i]);
        propagateSignals();
    }
}

function setupExport() {
    const btn = document.getElementById('export-img-btn');
    if (!btn) return;

    btn.addEventListener('click', () => {
        const element = document.getElementById('world');
        
        // Temporarily reset transform to capture full area correctly
        const originalTransform = element.style.transform;
        element.style.transform = 'none';
        
        // Find bounds of all gates to crop
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        if (STATE.gates.length === 0) {
            minX = 0; minY = 0; maxX = 800; maxY = 600;
        } else {
            STATE.gates.forEach(g => {
                minX = Math.min(minX, g.x);
                minY = Math.min(minY, g.y);
                maxX = Math.max(maxX, g.x + (GATE_DEFS[g.type].width || 60));
                maxY = Math.max(maxY, g.y + (GATE_DEFS[g.type].height || 40));
            });
            // Add padding
            minX -= 50; minY -= 50; maxX += 50; maxY += 50;
        }

        html2canvas(element, {
            backgroundColor: document.documentElement.classList.contains('dark') ? '#0f172a' : '#f8fafc',
            x: minX,
            y: minY,
            width: maxX - minX,
            height: maxY - minY,
            logging: false
        }).then(canvas => {
            // Restore transform
            element.style.transform = originalTransform;
            
            // Download
            const link = document.createElement('a');
            link.download = 'uklad-logiczny.png';
            link.href = canvas.toDataURL();
            link.click();
        }).catch(err => {
            console.error(err);
            element.style.transform = originalTransform;
            alert('Błąd podczas eksportu obrazu.');
        });
    });
}

// --- Initialization ---

function init() {
    setupDragAndDrop();
    setupCanvasInteractions();
    setupTheme();
    setupButtons();
    setupZoomControls();
    setupChat();
    setupMobileInteractions();
    setupCedarConverter();
    setupShortcuts();
    setupExamples();
    setupTruthTable();
    setupExport();
    centerView();
    
    // Initial State for Undo/Redo
    saveState();
    
    // Start Clock
    setInterval(() => {
        let changed = false;
        STATE.gates.forEach(gate => {
            if (gate.type === 'CLOCK') {
                gate.state.value = gate.state.value ? 0 : 1;
                changed = true;
            }
        });
        
        if (changed) {
            propagateSignals();
        }
    }, 1000); // 1Hz Clock

    // Oscilloscope Sampling (50Hz)
    setInterval(() => {
        if (!STATE.oscilloscope.paused) {
            const snapshot = {};
            STATE.gates.forEach(g => {
                if (g.type === 'PROBE') {
                    snapshot[g.id] = g.state.value || 0;
                }
            });
            STATE.oscilloscope.history.push(snapshot);
            if (STATE.oscilloscope.history.length > STATE.oscilloscope.maxHistory) {
                STATE.oscilloscope.history.shift();
            }
        }
    }, 20);

    // Render Loop
    function loop() {
        renderOscilloscope();
        renderFlowAnimation();
        requestAnimationFrame(loop);
    }
    loop();
}

function renderFlowAnimation() {
    const canvas = document.getElementById('animation-layer');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Clear canvas (using fixed 10000x10000 resolution)
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    if (!STATE.animation || !STATE.animation.active) return;
    
    // Spawn packets
    const now = Date.now();
    const speedInput = document.getElementById('anim-speed');
    const speedVal = speedInput ? parseInt(speedInput.value) : 5;
    const spawnRate = Math.max(50, 1000 / speedVal); // ms between spawns
    
    if (now - STATE.animation.lastSpawn > spawnRate) {
        STATE.connections.forEach(conn => {
            // Check if connection is active
            const sourceGate = STATE.gates.find(g => g.id === conn.from.gateId);
            if (sourceGate && sourceGate.outputs[conn.from.portIndex] === 1) {
                const pathId = `conn-${conn.from.gateId}-${conn.from.portIndex}-${conn.to.gateId}-${conn.to.portIndex}`;
                STATE.animation.packets.push({
                    pathId: pathId,
                    t: 0,
                    speed: 0.002 * speedVal // Speed factor
                });
            }
        });
        STATE.animation.lastSpawn = now;
    }
    
    // Update and Draw packets
    STATE.animation.packets = STATE.animation.packets.filter(p => {
        p.t += p.speed;
        if (p.t >= 1) return false;
        
        const pathEl = document.getElementById(p.pathId);
        if (pathEl) {
            try {
                const len = pathEl.getTotalLength();
                const point = pathEl.getPointAtLength(p.t * len);
                
                ctx.beginPath();
                ctx.arc(point.x, point.y, 4, 0, Math.PI * 2);
                ctx.fillStyle = '#fbbf24'; // Amber-400
                ctx.shadowColor = '#f59e0b';
                ctx.shadowBlur = 4;
                ctx.fill();
                ctx.shadowBlur = 0;
            } catch (e) {
                // Path might not exist yet or error
            }
        }
        return true;
    });
}

function centerView() {
    const rect = canvas.getBoundingClientRect();
    STATE.view.x = rect.width / 2;
    STATE.view.y = rect.height / 2;
    updateTransform();
}

function updateTransform() {
    world.style.transform = `translate(${STATE.view.x}px, ${STATE.view.y}px) scale(${STATE.view.zoom})`;
    
    // Update zoom text
    const zoomText = document.getElementById('zoom-reset');
    if (zoomText) zoomText.innerText = `${Math.round(STATE.view.zoom * 100)}%`;
}

function toWorldCoordinates(clientX, clientY) {
    const rect = canvas.getBoundingClientRect();
    const x = (clientX - rect.left - STATE.view.x) / STATE.view.zoom;
    const y = (clientY - rect.top - STATE.view.y) / STATE.view.zoom;
    return { x, y };
}

// --- Drag & Drop (Sidebar to Canvas) ---

function setupDragAndDrop() {
    const sidebarItems = document.querySelectorAll('.gate-item');
    
    sidebarItems.forEach(item => {
        item.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('type', item.dataset.type);
            e.dataTransfer.effectAllowed = 'copy';
        });
    });

    canvas.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    });

    canvas.addEventListener('drop', (e) => {
        e.preventDefault();
        const type = e.dataTransfer.getData('type');
        if (type && GATE_DEFS[type]) {
            const coords = toWorldCoordinates(e.clientX, e.clientY);
            createGate(type, coords.x, coords.y);
        }
    });
}

// --- Core Logic: Create Gate ---

function createGate(type, x, y) {
    const def = GATE_DEFS[type];
    const gate = {
        id: STATE.nextId++,
        type: type,
        x: snapToGrid(x - 30), // Center roughly
        y: snapToGrid(y - 20),
        inputs: Array(def.inputs).fill(0),
        outputs: Array(def.outputs).fill(0),
        state: { value: 0 } // Internal state for INPUT/OUTPUT
    };
    
    STATE.gates.push(gate);
    updateStats();
    renderGate(gate);
    saveState();
}

function snapToGrid(val) {
    return Math.round(val / CONFIG.gridSize) * CONFIG.gridSize;
}

// --- Rendering ---

function renderGate(gate) {
    const def = GATE_DEFS[gate.type];
    const el = document.createElement('div');
    el.className = 'gate-component';
    el.id = `gate-${gate.id}`;
    el.style.left = `${gate.x}px`;
    el.style.top = `${gate.y}px`;

    if (def.width) el.style.width = `${def.width}px`;
    if (def.height) el.style.height = `${def.height}px`;

    el.dataset.id = gate.id;

    // Delete Button
    const deleteBtn = document.createElement('div');
    deleteBtn.className = 'delete-btn';
    deleteBtn.innerHTML = '<span class="material-symbols-rounded text-[14px]">delete</span>';
    deleteBtn.onclick = (e) => {
        e.stopPropagation();
        removeGate(gate.id);
    };
    el.appendChild(deleteBtn);

    // Content
    const body = document.createElement('div');
    body.className = 'gate-body flex flex-col items-center';
    
    if (def.render) {
        body.innerHTML = def.render(gate);
    } else if (def.svgPath) {
        const w = def.width || 60;
        const h = def.height || 40;
        body.innerHTML = `
            <svg width="${w}" height="${h}" viewBox="0 0 ${w} ${h}" class="stroke-slate-700 dark:stroke-slate-300 fill-none">
                ${def.svgPath}
                <text x="${w/2}" y="${h-2}" text-anchor="middle" class="fill-slate-500 dark:fill-slate-400 stroke-none text-[8px] font-sans opacity-0 hover:opacity-100 transition-opacity select-none pointer-events-none">${def.name}</text>
            </svg>
        `;
    } else {
        // Default SVG Icon placeholder
        body.innerHTML = `
            <svg width="40" height="30" viewBox="0 0 40 30" class="stroke-slate-700 dark:stroke-slate-300 fill-none stroke-2">
                <rect x="2" y="2" width="36" height="26" rx="4" />
                <text x="20" y="18" text-anchor="middle" class="fill-slate-700 dark:fill-slate-300 stroke-none text-[10px] font-sans">${def.name}</text>
            </svg>
        `;
    }
    
    el.appendChild(body);

    // Ports
    // Inputs (Left)
    for (let i = 0; i < def.inputs; i++) {
        const port = document.createElement('div');
        port.className = 'port port-input';
        
        if (def.customPorts && def.customPorts.in && def.customPorts.in[i]) {
            const pos = def.customPorts.in[i];
            port.style.left = `${pos.left}px`;
            port.style.top = `${pos.top}px`;
        } else {
            // Custom positioning for standard gates to match SVG lines
            let top;
            if (def.inputs === 1) {
                top = 20; // Center (50% of 40px)
            } else if (def.inputs === 2) {
                top = (i === 0) ? 10 : 30; // 25% and 75%
            } else {
                // Fallback for 3+ inputs
                top = (40 * (i + 1) / (def.inputs + 1));
            }
            port.style.top = `${top - 5}px`; // -5 to center the 10px port
            // left is handled by CSS (-5px)
        }
        
        port.dataset.portType = 'in';
        port.dataset.portIndex = i;
        port.dataset.gateId = gate.id;
        
        // Label if defined
        if (def.labels && def.labels.in && def.labels.in[i]) {
            port.title = def.labels.in[i];
        }

        el.appendChild(port);
    }

    // Outputs (Right)
    for (let i = 0; i < def.outputs; i++) {
        const port = document.createElement('div');
        port.className = 'port port-output';
        
        if (def.customPorts && def.customPorts.out && def.customPorts.out[i]) {
            const pos = def.customPorts.out[i];
            port.style.left = `${pos.left}px`;
            port.style.top = `${pos.top}px`;
        } else {
            // Custom positioning
            let top;
            if (def.outputs === 1) {
                top = 20;
            } else if (def.outputs === 2) {
                top = (i === 0) ? 10 : 30;
            } else {
                top = (40 * (i + 1) / (def.outputs + 1));
            }
            port.style.top = `${top - 5}px`;
            // left is handled by CSS (55px)
        }

        port.dataset.portType = 'out';
        port.dataset.portIndex = i;
        port.dataset.gateId = gate.id;

        if (def.labels && def.labels.out && def.labels.out[i]) {
            port.title = def.labels.out[i];
        }

        el.appendChild(port);
    }

    // Event Listeners for Gate Interaction
    el.addEventListener('mousedown', handleGateMouseDown);
    
    // Specific logic for INPUT toggle
    if (gate.type === 'INPUT') {
        const toggle = el.querySelector('[data-action="toggle"]');
        if (toggle) {
            toggle.addEventListener('mousedown', (e) => {
                e.stopPropagation(); // Prevent drag start
                gate.state.value = gate.state.value ? 0 : 1;
                toggle.innerText = gate.state.value;
                toggle.classList.toggle('on', gate.state.value);
                propagateSignals();
            });
        }
    }

    gatesLayer.appendChild(el);
}

function updateGateVisuals(gateId) {
    const gate = STATE.gates.find(g => g.id === gateId);
    if (!gate) return;
    
    const el = document.getElementById(`gate-${gateId}`);
    if (!el) return;

    const def = GATE_DEFS[gate.type];
    
    if (gate.type === 'OUTPUT') {
        const display = el.querySelector('.output-display');
        if (display) {
            display.innerText = gate.state.value;
            display.classList.toggle('on', gate.state.value === 1);
        }
    } else if (gate.type === 'RAM' || gate.type === 'ROM' || gate.type === 'CPU' || gate.type === 'BUFFER') {
        // Re-render content
        const body = el.querySelector('.gate-body');
        if (body) {
            const def = GATE_DEFS[gate.type];
            body.innerHTML = def.render(gate);
        }
    } else if (gate.type === 'CLOCK') {
        const display = el.querySelector('.clock-display');
        if (display) {
            display.classList.toggle('on', gate.state.value === 1);
            display.classList.toggle('bg-blue-100', gate.state.value === 1);
            display.classList.toggle('bg-white', gate.state.value === 0);
        }
    } else if (gate.type === 'HEX_DISPLAY') {
        const val = gate.state.value || 0;
        // Segment map for 0-F
        // a,b,c,d,e,f,g (1 = on)
        const segments = [
            0x7E, // 0: 1111110
            0x30, // 1: 0110000
            0x6D, // 2: 1101101
            0x79, // 3: 1111001
            0x33, // 4: 0110011
            0x5B, // 5: 1011011
            0x5F, // 6: 1011111
            0x70, // 7: 1110000
            0x7F, // 8: 1111111
            0x7B, // 9: 1111011
            0x77, // A: 1110111
            0x1F, // b: 0011111
            0x4E, // C: 1001110
            0x3D, // d: 0111101
            0x4F, // E: 1001111
            0x47  // F: 1000111
        ];
        
        const pattern = segments[val] || 0;
        const segIds = ['a', 'b', 'c', 'd', 'e', 'f', 'g'];
        
        segIds.forEach((seg, i) => {
            const isOn = (pattern >> (6 - i)) & 1;
            const path = el.querySelector(`#seg-${seg}-${gate.id}`);
            if (path) {
                path.setAttribute('fill', isOn ? '#ef4444' : '#334155');
                path.setAttribute('filter', isOn ? 'drop-shadow(0 0 2px #ef4444)' : 'none');
            }
        });
    }
    
    // Update port active states
    const inputs = el.querySelectorAll('.port-input');
    inputs.forEach((port, idx) => {
        port.classList.toggle('active', gate.inputs[idx] === 1);
    });
    
    const outputs = el.querySelectorAll('.port-output');
    outputs.forEach((port, idx) => {
        port.classList.toggle('active', gate.outputs[idx] === 1);
    });
}

function renderConnections() {
    // Clear existing
    connectionsLayer.innerHTML = '';

    STATE.connections.forEach(conn => {
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        const d = calculatePath(conn);
        path.setAttribute('d', d);
        path.setAttribute('fill', 'none');
        path.classList.add('connection-line');
        
        // Unique ID for animation
        path.id = `conn-${conn.from.gateId}-${conn.from.portIndex}-${conn.to.gateId}-${conn.to.portIndex}`;
        
        // Check if active (based on source gate output)
        const sourceGate = STATE.gates.find(g => g.id === conn.from.gateId);
        if (sourceGate && sourceGate.outputs[conn.from.portIndex] === 1) {
            path.classList.add('active');
        }

        // Click to delete
        path.addEventListener('click', (e) => {
            e.stopPropagation();
            removeConnection(conn);
        });

        connectionsLayer.appendChild(path);
    });

    // Render temp connection if dragging
    if (STATE.tempConnection) {
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        let start, end;
        
        const portPos = getPortPosition(STATE.tempConnection.gateId, STATE.tempConnection.portIndex, STATE.tempConnection.type);
        start = toSvgCoords(portPos);
        end = toSvgCoords({ x: STATE.tempConnection.x, y: STATE.tempConnection.y });
        
        // If dragging from input, swap start/end for visual consistency (always out -> in)
        let d;
        if (STATE.tempConnection.type === 'in') {
             d = calculateBezier(end, start);
        } else {
             d = calculateBezier(start, end);
        }
        
        path.setAttribute('d', d);
        path.setAttribute('class', 'connection-line');
        path.style.strokeOpacity = '0.5';
        path.style.pointerEvents = 'none';
        connectionsLayer.appendChild(path);
    }
}

function toSvgCoords(pos) {
    return { x: pos.x + 5000, y: pos.y + 5000 };
}

function calculatePath(conn) {
    const start = getPortPosition(conn.from.gateId, conn.from.portIndex, 'out');
    const end = getPortPosition(conn.to.gateId, conn.to.portIndex, 'in');
    return calculateBezier(toSvgCoords(start), toSvgCoords(end));
}

function calculateBezier(start, end) {
    const dist = Math.abs(end.x - start.x) * 0.5;
    const cp1 = { x: start.x + Math.max(dist, 50), y: start.y };
    const cp2 = { x: end.x - Math.max(dist, 50), y: end.y };
    return `M ${start.x} ${start.y} C ${cp1.x} ${cp1.y}, ${cp2.x} ${cp2.y}, ${end.x} ${end.y}`;
}

function getPortPosition(gateId, portIndex, type) {
    const el = document.getElementById(`gate-${gateId}`);
    if (!el) return { x: 0, y: 0 };
    
    const port = el.querySelector(`.port-${type === 'in' ? 'input' : 'output'}[data-port-index="${portIndex}"]`);
    if (!port) return { x: 0, y: 0 };

    // Get position relative to gate
    const gateX = parseFloat(el.style.left) || 0;
    const gateY = parseFloat(el.style.top) || 0;
    
    // Use offsetLeft/Top which gives position relative to the gate-component
    // We add 5 to get the center of the 10x10 port
    return {
        x: gateX + port.offsetLeft + 5,
        y: gateY + port.offsetTop + 5
    };
}

// --- Interaction Handlers ---

function handleGateMouseDown(e) {
    // If clicked on port, handle connection
    if (e.target.classList.contains('port')) {
        e.stopPropagation();
        
        // Check if we are in click-to-connect mode and finishing a connection
        if (STATE.connectionMode === 'click' && STATE.tempConnection) {
            finishConnection(e.target);
            STATE.tempConnection = null;
            STATE.connectionMode = null;
            renderConnections();
            return;
        }

        // Start new connection (drag or click)
        startConnectionDrag(e.target);
        STATE.connectionMode = 'drag'; // Default to drag, switch to click if mouseup happens quickly
        return;
    }

    // Else handle gate move
    const gateEl = e.currentTarget;
    const gateId = parseInt(gateEl.dataset.id);
    
    // Selection
    if (STATE.selectedGateId && STATE.selectedGateId !== gateId) {
        const prev = document.getElementById(`gate-${STATE.selectedGateId}`);
        if (prev) prev.classList.remove('ring-2', 'ring-primary');
    }
    STATE.selectedGateId = gateId;
    gateEl.classList.add('ring-2', 'ring-primary');

    STATE.isDragging = true;
    STATE.draggedGateId = gateId;
    
    // Calculate offset in world coordinates
    const coords = toWorldCoordinates(e.clientX, e.clientY);
    const gate = STATE.gates.find(g => g.id === gateId);
    
    STATE.dragOffset = {
        x: coords.x - gate.x,
        y: coords.y - gate.y
    };
    
    gateEl.classList.add('dragging');
}

function startConnectionDrag(portEl) {
    const gateId = parseInt(portEl.dataset.gateId);
    const portIndex = parseInt(portEl.dataset.portIndex);
    const type = portEl.dataset.portType; // 'in' or 'out'
    

    
    // Initial position is the port position
    const pos = getPortPosition(gateId, portIndex, type);
    
    STATE.tempConnection = {
        gateId,
        portIndex,
        type,
        x: pos.x,
        y: pos.y
    };
}

function finishConnection(targetPortEl) {
    const targetGateId = parseInt(targetPortEl.dataset.gateId);
    const targetPortIndex = parseInt(targetPortEl.dataset.portIndex);
    const targetType = targetPortEl.dataset.portType;
    
    const source = STATE.tempConnection;
    
    // Validation
    if (source.gateId === targetGateId) return; // Same gate
    if (source.type === targetType) return; // Same type (in-in or out-out)
    
    // Normalize to from -> to
    let from, to;
    if (source.type === 'out') {
        from = { gateId: source.gateId, portIndex: source.portIndex };
        to = { gateId: targetGateId, portIndex: targetPortIndex };
    } else {
        from = { gateId: targetGateId, portIndex: targetPortIndex };
        to = { gateId: source.gateId, portIndex: source.portIndex };
    }
    
    // Check if connection exists
    const exists = STATE.connections.some(c => 
        c.to.gateId === to.gateId && c.to.portIndex === to.portIndex
    );
    
    // Allow only one connection per input
    if (exists) {
        // Remove old connection to this input
        STATE.connections = STATE.connections.filter(c => 
            !(c.to.gateId === to.gateId && c.to.portIndex === to.portIndex)
        );
    }
    
    STATE.connections.push({ from, to });
    updateStats();
    propagateSignals();
    renderConnections();
}

function removeGate(id) {
    STATE.gates = STATE.gates.filter(g => g.id !== id);
    // Remove associated connections
    STATE.connections = STATE.connections.filter(c => 
        c.from.gateId !== id && c.to.gateId !== id
    );
    
    const el = document.getElementById(`gate-${id}`);
    if (el) el.remove();
    
    updateStats();
    renderConnections();
}

function removeConnection(conn) {
    STATE.connections = STATE.connections.filter(c => c !== conn);
    updateStats();
    propagateSignals(); // Re-calc logic
    renderConnections();
}

// --- Logic Propagation ---

function propagateSignals() {
    // 1. Reset all inputs/outputs (except INPUT gates)
    STATE.gates.forEach(g => {
        if (g.type !== 'INPUT') {
            g.inputs.fill(0);
            // Don't reset outputs yet, they are derived
        }
    });

    // 2. Topological Sort or Iterative Propagation
    // Simple iterative approach: max depth 50 to prevent infinite loops
    
    let changed = true;
    let iterations = 0;
    
    while (changed && iterations < 50) {
        changed = false;
        
        // Apply connections: Transfer outputs to inputs
        STATE.connections.forEach(conn => {
            const sourceGate = STATE.gates.find(g => g.id === conn.from.gateId);
            const targetGate = STATE.gates.find(g => g.id === conn.to.gateId);
            
            if (sourceGate && targetGate) {
                const val = sourceGate.outputs[conn.from.portIndex];
                if (targetGate.inputs[conn.to.portIndex] !== val) {
                    targetGate.inputs[conn.to.portIndex] = val;
                    changed = true;
                }
            }
        });
        
        // Compute gate logic
        STATE.gates.forEach(gate => {
            const def = GATE_DEFS[gate.type];
            if (def.logic) {
                let result;
                if (gate.type === 'INPUT') {
                    result = def.logic(null, gate.state);
                } else if (gate.type === 'OUTPUT') {
                    result = def.logic(gate.inputs);
                    gate.state.value = result.value; // Update visual state
                } else {
                    result = def.logic(gate.inputs, gate.state);
                }
                
                // Update outputs
                if (result) {
                    for (let i = 0; i < gate.outputs.length; i++) {
                        const key = `out${i}`;
                        if (result[key] !== undefined && gate.outputs[i] !== result[key]) {
                            gate.outputs[i] = result[key];
                            changed = true;
                        }
                    }
                }
            }
        });
        
        iterations++;
    }
    
    // 3. Update Visuals
    STATE.gates.forEach(g => updateGateVisuals(g.id));
    renderConnections();
}

// --- UI Helpers ---

function updateStats() {
    statsGates.innerText = STATE.gates.length;
    statsConnections.innerText = STATE.connections.length;
}

function saveWorkspace() {
    const data = {
        gates: STATE.gates,
        connections: STATE.connections,
        nextId: STATE.nextId,
        view: STATE.view
    };
    
    const json = JSON.stringify(data, null, 2);
    const blob = new Blob([json], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = 'logic-circuit.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function importCedarLogic(xmlString) {
    const parser = new DOMParser();
    const xmlDoc = parser.parseFromString(xmlString, "text/xml");
    
    const components = xmlDoc.getElementsByTagName("component");
    const connections = xmlDoc.getElementsByTagName("connection");
    
    const newGates = [];
    const newConnections = [];
    const idMap = {}; // cedarID -> myID

    // Reverse Type Map
    const cedarToMyType = {
        'TOGGLE_SWITCH': 'INPUT',
        'LED': 'OUTPUT',
        'INV': 'NOT',
        'AND2': 'AND',
        'OR2': 'OR',
        'NAND2': 'NAND',
        'NOR2': 'NOR',
        'XOR2': 'XOR',
        'XNOR2': 'XNOR',
        'BUFFER': 'BUFFER',
        'CLOCK': 'CLOCK',
        'D_FLIP_FLOP': 'D_FF',
        'JK_FLIP_FLOP': 'JK_FF'
    };

    // 1. Import Gates
    for (let i = 0; i < components.length; i++) {
        const comp = components[i];
        const cedarId = comp.getAttribute('id');
        const cedarType = comp.getAttribute('type');
        const x = parseFloat(comp.getAttribute('x'));
        const y = parseFloat(comp.getAttribute('y'));
        
        let myType = cedarToMyType[cedarType];
        
        // Fallback for multi-input gates (map to 2-input versions for now)
        if (!myType) {
            if (cedarType.startsWith('AND')) myType = 'AND';
            else if (cedarType.startsWith('OR')) myType = 'OR';
            else if (cedarType.startsWith('NAND')) myType = 'NAND';
            else if (cedarType.startsWith('NOR')) myType = 'NOR';
            else if (cedarType.startsWith('XOR')) myType = 'XOR';
            else if (cedarType.startsWith('XNOR')) myType = 'XNOR';
        }

        if (myType && GATE_DEFS[myType]) {
            const def = GATE_DEFS[myType];
            const newId = STATE.nextId++;
            idMap[cedarId] = newId;
            
            const gate = {
                id: newId,
                type: myType,
                x: x,
                y: y,
                inputs: Array(def.inputs).fill(0),
                outputs: Array(def.outputs).fill(0),
                state: { value: 0 }
            };
            
            newGates.push(gate);
        } else {
            console.warn(`Unknown Cedar type: ${cedarType}`);
        }
    }

    // 2. Import Connections
    for (let i = 0; i < connections.length; i++) {
        const conn = connections[i];
        const fromGateId = conn.getAttribute('from_gate');
        const fromPin = parseInt(conn.getAttribute('from_pin'));
        const toGateId = conn.getAttribute('to_gate');
        const toPin = parseInt(conn.getAttribute('to_pin'));
        
        const myFromId = idMap[fromGateId];
        const myToId = idMap[toGateId];
        
        if (myFromId && myToId) {
            newConnections.push({
                from: { gateId: myFromId, portIndex: fromPin },
                to: { gateId: myToId, portIndex: toPin }
            });
        }
    }

    // 3. Apply State
    STATE.gates = newGates;
    STATE.connections = newConnections;
    
    // Re-render
    gatesLayer.innerHTML = '';
    connectionsLayer.innerHTML = '';
    
    STATE.gates.forEach(gate => renderGate(gate));
    renderConnections();
    updateStats();
    propagateSignals();
    centerView();
    saveState();
    
    alert('Zaimportowano plik Cedar Logic!');
}

function loadWorkspace(file) {
    const reader = new FileReader();
    reader.onload = (e) => {
        try {
            const content = e.target.result;
            
            // Check for XML (Cedar Logic)
            if (content.trim().startsWith('<')) {
                importCedarLogic(content);
                return;
            }

            const data = JSON.parse(content);
            
            // Basic validation
            if (!data.gates || !data.connections) {
                alert('Nieprawidłowy format pliku!');
                return;
            }
            
            // Clear current state
            STATE.gates = [];
            STATE.connections = [];
            gatesLayer.innerHTML = '';
            connectionsLayer.innerHTML = '';
            
            // Restore state
            STATE.nextId = data.nextId || 1;
            
            // Restore gates
            data.gates.forEach(gate => {
                // Ensure gate type exists
                if (GATE_DEFS[gate.type]) {
                    STATE.gates.push(gate);
                    renderGate(gate);
                }
            });
            
            // Restore connections
            STATE.connections = data.connections;
            
            // Restore view if available
            if (data.view) {
                STATE.view = data.view;
                updateTransform();
            }
            
            updateStats();
            propagateSignals();
            renderConnections();
            
        } catch (err) {
            console.error(err);
            alert('Błąd podczas wczytywania pliku!');
        }
    };
    reader.readAsText(file);
}

function setupTheme() {
    const btn = document.getElementById('theme-toggle-btn');
    const icon = btn.querySelector('span');
    
    // Check local storage or system preference
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
        icon.innerText = 'light_mode';
    } else {
        document.documentElement.classList.remove('dark');
        icon.innerText = 'dark_mode';
    }

    btn.addEventListener('click', () => {
        if (document.documentElement.classList.contains('dark')) {
            document.documentElement.classList.remove('dark');
            localStorage.theme = 'light';
            icon.innerText = 'dark_mode';
        } else {
            document.documentElement.classList.add('dark');
            localStorage.theme = 'dark';
            icon.innerText = 'light_mode';
        }
    });
}

function setupButtons() {
    document.getElementById('clear-canvas-btn').addEventListener('click', () => {
        STATE.gates = [];
        STATE.connections = [];
        STATE.nextId = 1;
        gatesLayer.innerHTML = '';
        connectionsLayer.innerHTML = '';
        updateStats();
    });

    // Save & Load
    document.getElementById('save-btn').addEventListener('click', saveWorkspace);
    
    const fileInput = document.getElementById('load-file-input');
    document.getElementById('load-btn').addEventListener('click', () => {
        fileInput.click();
    });
    
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            loadWorkspace(e.target.files[0]);
            e.target.value = ''; // Reset input
        }
    });

    document.getElementById('test-signal-btn').addEventListener('click', () => {
        // Toggle Animation
        STATE.animation.active = !STATE.animation.active;
        
        const btn = document.getElementById('test-signal-btn');
        if (STATE.animation.active) {
            // Start
            propagateSignals();
            btn.innerHTML = '<span class="material-symbols-rounded animate-spin">refresh</span> Stop Testing';
            btn.classList.remove('bg-success', 'hover:bg-emerald-600');
            btn.classList.add('bg-warning', 'hover:bg-red-600');
        } else {
            // Stop
            STATE.animation.packets = [];
            btn.innerHTML = '<span class="material-symbols-rounded text-lg">play_arrow</span> Testuj Sygnał';
            btn.classList.remove('bg-warning', 'hover:bg-red-600');
            btn.classList.add('bg-success', 'hover:bg-emerald-600');
            
            // Clear canvas
            const canvas = document.getElementById('animation-layer');
            if (canvas) {
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }
        }
    });

    // Help Modal
    const modal = document.getElementById('help-modal');
    document.getElementById('help-btn').addEventListener('click', () => {
        modal.classList.remove('hidden');
    });
    document.getElementById('close-help-btn').addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    // Oscilloscope Controls
    const oscToggleBtn = document.getElementById('osc-toggle-btn');
    const mainLayout = document.getElementById('main-layout');
    const oscIcon = document.getElementById('osc-toggle-icon');
    let oscCollapsed = true;

    oscToggleBtn.addEventListener('click', () => {
        oscCollapsed = !oscCollapsed;
        if (oscCollapsed) {
            mainLayout.style.gridTemplateRows = '1fr 32px';
            oscIcon.style.transform = 'rotate(-90deg)';
        } else {
            mainLayout.style.gridTemplateRows = '1fr 200px';
            oscIcon.style.transform = 'rotate(0deg)';
        }
        // Resize canvas after transition
        setTimeout(renderOscilloscope, 310);
    });

    document.getElementById('osc-pause-btn').addEventListener('click', (e) => {
        STATE.oscilloscope.paused = !STATE.oscilloscope.paused;
        e.currentTarget.querySelector('span').innerText = STATE.oscilloscope.paused ? 'play_arrow' : 'pause';
    });
    document.getElementById('osc-clear-btn').addEventListener('click', () => {
        STATE.oscilloscope.history = [];
    });
}

init();
