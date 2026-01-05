document.addEventListener("DOMContentLoaded", () => {
  const inputText = document.getElementById("input-text");
  const previewContent = document.getElementById("preview-content");
  const filenameInput = document.getElementById("filename-input");
  const printBtn = document.getElementById("print-btn");

  // Format text function
  function formatText(text) {
    if (!text.trim()) {
      return '<div class="placeholder-text">Formatted preview will appear here...</div>';
    }

    let html = text;

    // 1. Remove separator lines (------)
    html = html.replace(/^\s*-{3,}\s*$/gm, "");

    // 2. Escape HTML characters (basic security)
    html = html
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");

    // 3. Process line by line
    const lines = html.split("\n");
    let formattedHtml = "";
    let inList = false;
    let listType = null;

    lines.forEach((line, index) => {
      let content = line.trim();

      // Handle empty lines
      if (!content) {
        if (inList) {
          formattedHtml += `</${listType}>`;
          inList = false;
          listType = null;
        }
        return;
      }

      // --- Header Detection ---
      // Regex for "1. Title", "1.1. Title"
      const isMainHeader = content.match(/^(\d+)\.\s+(.+)/);
      const isSubHeader = content.match(/^(\d+\.\d+)\.?\s+(.+)/);

      // Heuristic: Short all-caps lines are likely headers
      const isAllCapsHeader =
        content.length < 60 &&
        content === content.toUpperCase() &&
        content.length > 3 &&
        !content.match(/[.!?]$/);

      if (isSubHeader) {
        if (inList) {
          formattedHtml += `</${listType}>`;
          inList = false;
        }
        formattedHtml += `<h3>${content}</h3>`;
        return;
      }

      if (isMainHeader) {
        // If short enough, treat as H2
        if (content.length < 100) {
          if (inList) {
            formattedHtml += `</${listType}>`;
            inList = false;
          }
          formattedHtml += `<h2>${content}</h2>`;
          return;
        }
      }

      if (isAllCapsHeader) {
        if (inList) {
          formattedHtml += `</${listType}>`;
          inList = false;
        }
        formattedHtml += `<h3>${content}</h3>`;
        return;
      }

      // --- List Detection ---
      // Bullets: - or *
      if (content.match(/^[\*\-]\s/)) {
        if (!inList || listType !== "ul") {
          if (inList) formattedHtml += `</${listType}>`;
          formattedHtml += "<ul>";
          inList = true;
          listType = "ul";
        }
        formattedHtml += `<li>${content.replace(/^[\*\-]\s+/, "")}</li>`;
        return;
      }

      // Numbered: 1.
      if (content.match(/^\d+\.\s/)) {
        if (!inList || listType !== "ol") {
          if (inList) formattedHtml += `</${listType}>`;
          formattedHtml += "<ol>";
          inList = true;
          listType = "ol";
        }
        formattedHtml += `<li>${content.replace(/^\d+\.\s+/, "")}</li>`;
        return;
      }

      // Close list if we hit normal text
      if (inList) {
        formattedHtml += `</${listType}>`;
        inList = false;
      }

      // --- Inline Formatting ---
      // Bold: **text**
      content = content.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
      // Italic: *text*
      content = content.replace(/\*(.*?)\*/g, "<em>$1</em>");

      // --- Paragraph vs Title ---
      // First line is H1 if it looks like a title
      if (index === 0 && content.length < 100 && !content.match(/[.!?]$/)) {
        formattedHtml += `<h1>${content}</h1>`;
      } else {
        formattedHtml += `<p>${content}</p>`;
      }
    });

    if (inList) {
      formattedHtml += `</${listType}>`;
    }

    return formattedHtml;
  }

  // Detect filename from first line
  function detectFilename(text) {
    if (!text) return "";
    const firstLine = text.split("\n")[0].trim();
    // Clean up filename
    return firstLine.replace(/[^a-z0-9\s\-_]/gi, "").substring(0, 50);
  }

  // Input Handler
  inputText.addEventListener("input", (e) => {
    const text = e.target.value;
    previewContent.innerHTML = formatText(text);

    const detectedName = detectFilename(text);

    // Update document title (for PDF filename)
    if (filenameInput.value) {
      document.title = filenameInput.value;
    } else if (detectedName) {
      document.title = detectedName;
      filenameInput.placeholder = detectedName;
    } else {
      document.title = "Text Formatter";
    }
  });

  // Filename Input Handler
  filenameInput.addEventListener("input", (e) => {
    if (e.target.value) {
      document.title = e.target.value;
    } else {
      const text = inputText.value;
      document.title = detectFilename(text) || "Text Formatter";
    }
  });

  // Print Handler
  printBtn.addEventListener("click", () => {
    window.print();
  });
});
