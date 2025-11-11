import RqcGradeReviewsButton from "../../resources/js/components/RqcGradeReviewsButton.vue";
pkp.registry.registerComponent("RqcGradeReviewsButton", RqcGradeReviewsButton);

pkp.registry.storeExtend("workflow", (piniaContext) => {
  const workflowStore = piniaContext.store;

  workflowStore.extender.extendFn("getActionItems", (primaryItems, args) => {
    if (
      args?.selectedMenuState?.primaryMenuItem === "workflow" &&
      args?.selectedMenuState?.stageId ===
        pkp.const.WORKFLOW_STAGE_ID_EXTERNAL_REVIEW || 
        args?.selectedMenuState?.stageId === pkp.const.WORKFLOW_STAGE_ID_INTERNAL_REVIEW
    ) {
      return [
        ...primaryItems,
        {
          component: "RqcGradeReviewsButton",
          props: { submission: args.submission },
        },
      ];
    } else {
      return primaryItems;
    }
  });
});