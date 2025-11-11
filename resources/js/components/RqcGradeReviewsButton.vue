<template>
  <div>
    <PkpButton @click="openEditorActionRqcGradeModal">{{
      t("plugins.generic.rqc.editoraction.grade.button")
    }}</PkpButton>
  </div>
</template>

<script setup>
const { useModal } = pkp.modules.useModal;
const { useLocalize } = pkp.modules.useLocalize;

const { t } = useLocalize();
const { openDialog } = useModal();

const { useFetch } = pkp.modules.useFetch;
const { useUrl } = pkp.modules.useUrl;
const { apiUrl } = useUrl("submissions/rqc/grade");

const urlParams = new URLSearchParams(window.location.search);
const submissionId = parseInt(urlParams.get('workflowSubmissionId'));

async function callRqcEditorDecisionHandler() {
  const { fetch, error, data } = useFetch(apiUrl, {
      method: "POST",
      body: {
          submissionId: submissionId
      }
  });
  console.log("Calling RQC Editor Decision Handler...");
  await fetch();
  if (error?.value) {
      console.error("Error:", error.value);
      alert("Error: " + JSON.stringify(error.value));
      return;
  }
  if (data?.value?.success) {
      alert("Sending data to RQC was successful.");
  } else {
      console.error("Request failed", data?.value);
      alert("Request failed: " + (data?.value?.error || "Unknown error"));
  }
}


function openEditorActionRqcGradeModal() {
  console.log("Opening RQC Grade Modal");
  openDialog({
    title: t("plugins.generic.rqc.editoraction.grade.title"),
    message: t("plugins.generic.rqc.editoraction.grade.explanation"),
    actions: [
      {
        label: t("plugins.generic.rqc.editoraction.grade.proceed"),
        isPrimary: true,
        callback: (close) => {
          // Call the handler to process the RQC grade action
          // An explicit call to RQC will be initiated
          callRqcEditorDecisionHandler();
          close();
        },
      },
      {
        label: t("plugins.generic.rqc.editoraction.grade.cancel"),
        isWarnable: true,
        callback: (close) => {
          close();
          // Close the modal
        },
      },
    ],
  });
}
</script>