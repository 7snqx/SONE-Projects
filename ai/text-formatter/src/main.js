// CSS is loaded via <link> in index.html to avoid MIME type issues

document.addEventListener("DOMContentLoaded", () => {
  console.log("Text Formatter: Initializing...");

  const inputText = document.getElementById("input-text");
  const previewContent = document.getElementById("preview-content");
  const filenameInput = document.getElementById("filename-input");
  const downloadBtn = document.getElementById("download-btn");

  if (!inputText || !previewContent) {
    console.error("Text Formatter: Critical elements not found!");
    return;
  }

  // Function to format text
  function formatText(text) {
    if (!text.trim()) {
      return '<div class="placeholder-text">Formatted preview will appear here...</div>';
    }

    try {
      let html = text;

      // 1. Remove separator lines (------)
      // Matches lines that are just dashes (at least 3), allowing for whitespace
      html = html.replace(/^\s*-{3,}\s*$/gm, "");

      // 2. Escape HTML characters
      html = html
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");

      // 3. Process line by line
      const lines = html.split("\n");
      let formattedHtml = "";
      let inList = false;
      let listType = null; // 'ul' or 'ol'

      lines.forEach((line, index) => {
        let content = line.trim();

        // Skip empty lines, but close lists if open
        if (!content) {
          if (inList) {
            formattedHtml += `</${listType}>`;
            inList = false;
            listType = null;
          }
          return;
        }

        // Detect Lists
        // Check for headers first to avoid false positives
        const isMainHeader = content.match(/^(\d+)\.\s+(.+)/);
        const isSubHeader = content.match(/^(\d+\.\d+)\.?\s+(.+)/);

        if (isSubHeader) {
          if (inList) {
            formattedHtml += `</${listType}>`;
            inList = false;
          }
          formattedHtml += `<h3>${content}</h3>`;
          return;
        }

        if (isMainHeader) {
          // If the text following the number is short (< 100 chars), likely a header
          if (content.length < 100) {
            if (inList) {
              formattedHtml += `</${listType}>`;
              inList = false;
            }
            formattedHtml += `<h2>${content}</h2>`;
            return;
          }
        }

        // List detection
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

        // If we are here, it's not a list item.
        if (inList) {
          formattedHtml += `</${listType}>`;
          inList = false;
        }

        // Bold/Italic formatting (inline)
        content = content.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
        content = content.replace(/\*(.*?)\*/g, "<em>$1</em>");

        // Paragraph or Header?
        // Main title detection
        if (
          index === 0 ||
          (index < 5 && content.length < 80 && !content.match(/[.!?]$/))
        ) {
          formattedHtml += `<h1>${content}</h1>`;
        } else if (
          content.length < 60 &&
          !content.match(/[.!?]$/) &&
          content === content.toUpperCase()
        ) {
          // All caps short line -> H3
          formattedHtml += `<h3>${content}</h3>`;
        } else {
          formattedHtml += `<p>${content}</p>`;
        }
      });

      if (inList) {
        formattedHtml += `</${listType}>`;
      }

      return formattedHtml;
    } catch (e) {
      console.error("Formatting error:", e);
      return `<div class="error">Error formatting text: ${e.message}</div>`;
    }
  }

  // Function to detect filename
  function detectFilename(text) {
    if (!text) return "";
    const firstLine = text.split("\n")[0].trim();
    const safeName = firstLine.replace(/[^a-z0-9\s\-_]/gi, "").substring(0, 50);
    return safeName;
  }

  // Event Listeners
  inputText.addEventListener("input", (e) => {
    console.log("Input detected");
    const text = e.target.value;
    previewContent.innerHTML = formatText(text);

    if (!filenameInput.value) {
      filenameInput.placeholder =
        detectFilename(text) || "Filename (auto-detected)";
    }
  });

  if (downloadBtn) {
    downloadBtn.addEventListener("click", () => {
      console.log("Download clicked");
      const element = document.getElementById("preview-content");
      const text = inputText.value;

      let filename = filenameInput.value.trim();
      if (!filename) {
        filename = detectFilename(text) || "document";
      }
      if (!filename.toLowerCase().endsWith(".pdf")) {
        filename += ".pdf";
      }

      const opt = {
        margin: 0,
        filename: filename,
        image: { type: "jpeg", quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: "mm", format: "a4", orientation: "portrait" },
      };

      const originalShadow = element.style.boxShadow;
      const originalBg = element.style.backgroundColor;

      element.style.boxShadow = "none";
      element.style.backgroundColor = "#ffffff";

      if (typeof html2pdf !== "undefined") {
        html2pdf()
          .set(opt)
          .from(element)
          .save()
          .then(() => {
            element.style.boxShadow = originalShadow;
            element.style.backgroundColor = originalBg;
          })
          .catch((err) => {
            console.error("PDF generation error:", err);
            alert("Error generating PDF. Check console.");
            element.style.boxShadow = originalShadow;
            element.style.backgroundColor = originalBg;
          });
      } else {
        alert("PDF generation library not loaded.");
        element.style.boxShadow = originalShadow;
        element.style.backgroundColor = originalBg;
      }
    });
  }

  console.log("Text Formatter: Ready");
});
