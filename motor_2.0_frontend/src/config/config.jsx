import React, { useEffect } from "react";
import styled from "styled-components";
import { TabWrapper, Tab } from "components";
import Theme from "modules/Theme/Theme";
import { FieldConfig } from "modules";
import ProposalValidation from "modules/proposal/validations/ProposalValidation";
import { useDispatch, useSelector } from "react-redux";
import { configTab as ConfigTab } from "modules/Home/home.slice";
import QuestionForm from "components/modal/FaqConfig";

// prettier-ignore
const tabs = [
  { id: "theme", label: "Theme Config", component: Theme },
  { id: "field", label: "Field Config", component: FieldConfig },
  { id: "proposal-validation", label: "Proposal Validation", component: ProposalValidation },
  { id: "question-config", label: "FAQ", component: QuestionForm },
];

const Config = () => {
  const { configTab } = useSelector((state) => state.home);
  const dispatch = useDispatch();

  const handleTabClick = (tabId) => {
    dispatch(ConfigTab(tabId));
    sessionStorage.setItem("selectedTab", tabId);
  };

  useEffect(() => {
    const selectedTab = sessionStorage.getItem("selectedTab");
    if (selectedTab && tabs.find((tab) => tab.id === selectedTab)) {
      dispatch(ConfigTab(selectedTab));
    }
  }, [dispatch]);

  return (
    <>
      <Container>
        <TabWrapper width="290px" className="tabWrappers">
          {tabs.map((tab) => (
            <Tab
              key={tab.id}
              isActive={configTab === tab.id}
              onClick={() => handleTabClick(tab.id)}
              className="shareTab"
              shareTab="shareTab"
            >
              {tab.label}
            </Tab>
          ))}
        </TabWrapper>
      </Container>
      {tabs.map(
        (tab) => configTab === tab.id && <tab.component key={tab.id} />
      )}
    </>
  );
};

export default Config;

const Container = styled.div`
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 50px;
`;
